<?php

class BpmService
{
    //TODO:配置变量
    protected $bpmBasePath;

    function __construct(){
        $this->bpmBasePath = storage_path('app/bpm/difinitions');
    }

    public static function initProcess($definitionId, $userId, $name, $description)
    {
        $definition = BpmDefinition::find($definitionId);
        $definition->difination =
        //持久化process与第一步的activity
        $process = BpmProcess::create([
            'name' => $name,
            'description' => $description,
            'definition_id' => $definitionId,
//            'launch_user_id' => $userId,

            'field_json' => $defination->field_json,

        ]);
        //根据定义创建步骤
        $steps = $process->workflowDefination->workflowDefinationSteps;
        foreach ($steps as &$step) {
            $processStep = WorkflowProcessStep::create([
                'number' => $step->number,
                'name' => $step->name,
                'description' => $step->description,
                'type' => $step->type,
//                'next_executor_json' => $step->executor_exp,
                'next_step_json' => $step->next_step_json,
                'field_control_json' => $step->field_control_json,
                'user_control_json' => $step->user_control_json,

                'workflow_process_id' => $process->id,
                'execute_user_id' => $userId
            ]);
            //绑定start节点,录入数据库后绑定
            if ($step->type === 1) {
                $process->current_step_id = $processStep->id;
                $process->save();
            }
        }
//        });
        return $process;
    }

    public static function runProcess($process, $nextUserId = null, $args = [])
    {
        $processStep = $process->current_step;
        $nextStepConfig = json_decode($processStep->next_step_json, true);
        if (array_key_exists('to', $nextStepConfig)) {
            $content = $process->current_step->content_json;
            $lastStep = $process->current_step_id;
            $process->current_step_id = WorkflowProcessStep::where('workflow_process_id','=', $process->id)->where('number', '=',$nextStepConfig['to'])->first()->id;
            $process->save();

            $process = WorkflowProcess::find($process->id);
            $process->current_step->content_json = $content;
            $process->current_step->execute_user_id = $nextUserId;
            $process->current_step->last_step_id = $lastStep;

            $process->current_step->save();
            WorkflowService::runProcess($process, $nextUserId);
        } else if ($processStep->type === 2) {
            $process->status = 2;
            $process->save();
        } else if (array_key_exists('selection', $nextStepConfig) && array_key_exists('choiceId', $args)) {
            foreach ($nextStepConfig['selection'] as $selection) {
                if ($args['choiceId'] === $selection['id']) {
                    $content = $process->current_step->content_json;
                    $lastStep = $process->current_step_id;
                    $process->current_step_id = WorkflowProcessStep::where('workflow_process_id', $process->id)->where('number', $selection['to'])->first()->id;
                    $process->save();
                    //TODO:为什么需要重新取
                    $process = WorkflowProcess::find($process->id);
                    $process->current_step->content_json = $content;
                    $process->current_step->execute_user_id = $nextUserId;
                    $process->current_step->last_step_id = $lastStep;

                    $process->current_step->save();
                    WorkflowService::runProcess($process, $nextUserId);
                    break;
                }
            }


        } else if (array_key_exists('selection', $nextStepConfig)) {
            $nextExecutor = [];
            foreach ($nextStepConfig['selection'] as $selection) {
                if (array_key_exists('executors', $selection)) {
                    $executorsExp = $selection['executors'];
                    $executorIds = [];
                    if (array_key_exists('users', $executorsExp)) {
                        $newExecutors = User::wherein('id', $executorsExp['users'])->get(['id']);
                        foreach ($newExecutors as $newExecutor) {
                            $newExecutorIds[] = $newExecutor->id;
                        }
                        //TODO：array_unique默认地址传递？
                        $executorIds = array_unique(array_merge($executorIds, $newExecutorIds));
                    }
                    if (array_key_exists('roles', $executorsExp)) {
                        $roles = Role::wherein('id', $executorsExp['roles'])->get(['id']);
                        foreach ($roles as &$role) {
                            $newExecutors = $role->users;
                            foreach ($newExecutors as $newExecutor) {
                                $newExecutorIds[] = $newExecutor->id;
                            }
                            $executorIds = array_unique(array_merge($executorIds, $newExecutorIds));
                        }
                    }
                    if (array_key_exists('anchors', $executorsExp)) {
                        if ($executorsExp['anchors'] === null) {
                            $executorsExp['anchors'] = [];
                        }
                        foreach ($executorsExp['anchors'] as $anchor) {
                            $newExecutors = WorkflowService::interpretAnchor($anchor,$process);
                            $executorIds = array_unique(array_merge($executorIds, $newExecutors));
                        }
                    }
                    $nextExecutor[] = ['id' => $selection['id'], 'users' => array_merge($executorIds)];
                }
            }
            $process->current_step->next_executor_json = json_encode($nextExecutor);
            $process->current_step->save();
        }
        return $process;
    }

    public static function interpretAnchor($anchorName,$process){
        switch ($anchorName) {
            case 'leaders':
                $userIds = [];
                $roles = User::find(Auth::id())->roles;
                foreach ($roles as &$role) {
                    $department = $role->parent_role;
                    if ($department && $department->type === 11) {
                        $leaderRoles = Role::where('parent_id', '=', $department->id)->where('type', '=', 21)->get();
                        foreach ($leaderRoles as &$leaderRole) {
                            $newUsers = $leaderRole->users;
                            foreach ($newUsers as &$newUser) {
                                $userIds[] = $newUser->id;
                            }
                        }
                    } else {
                        continue;
                    }
                }
                return $userIds;
                break;
            case 'back':
//                $userIds = [];
                $userIds[] = $process->current_step->last_step->execute_user_id;
                return $userIds;
                break;
        }
    }

    public function getExecutors($process)
    {
        $nextStepConfig = json_decode($process->current_step->next_step_json, true);

    }

} 