<?php

class ProcessService
{
    //TODO:配置变量
    protected $definitionModel;
//    protected $definitionContent;
    protected $definitionFileParser;
    //TODO:不初始化，默认值,构造器不赋值怎么办
    protected $processModel;

    function __construct($bpmDefinition, $processId = null){
        //TODO:参数认证
        $this->definitionModel = $bpmDefinition;
        $difinitionFilePath = storage_path('app/bpm/definitions/'.$bpmDefinition->filename);
        $this->definitionFileParser = new DefinitionFileParser($difinitionFilePath);
        if($processId !== null){
            $this->processModel = BpmProcess::find($processId);
        }
    }

    public static function getUserAssocProcesses($userId, $includeEnded = false){
        //TODO:是否应该去除对user表的相关性,process只取部分字段,应当返回可json化的Process实体数组（process包含model）
        return User::find($userId)->processes;
    }

    //TODO:或许需要改为静态方法，工厂模式，创建对象自己
    public static function initProcess($definition, $userId, $name, $description)
    {
        $processService = new ProcessService($definition, $userId, $name, $description);
        //TODO:事务
        //持久化process
        $processService->processModel = BpmProcess::create([
            'definition_id' => $processService->definitionModel->id,
            'name' => $name,
            'description' => $description,
            'launch_user_id' => $userId,
//            'field_json' => $definition->field_json,
        ]);
        //添加观察者
        //TODO:关于括号，关于userId
        $processService->processModel->spectators()->attach($userId);
        //初始化第一步start的activity
        $activity = $processService->definitionFileParser->findStartActivityId();
        $content = $processService->definitionFileParser->findActivityContentById($activity);
        $processService->initActivity($activity,
            property_exists($content,'name')?$content->name:$activity,
            property_exists($content,'description')?$content->description:'',
            $userId);
        return $processService;
    }

    public function run(){
        $activities = $this->getActivities();
        foreach($activities as $activity){
            $this->doActivity($activity->id);
        }
    }

    public function getActivities(){
        return $this->processModel->activities;
    }

    public static function getUserAssocActivities($userId){
        //TODO:考虑关联关系
        return BpmActivity::where('user_id','=',$userId)->get();

    }

    public function takeSnapshot(){
        //TODO:事务
        //创建snapshot
        BpmSnapshot::create([
            'process_id' => $this->processModel->id,
            'variables' => $this->processModel->variables,
            'spectators' => $this->processModel->spectators,
        ]);
        //快照activities
        $currentActivityCollection = $this->processModel->activities;
        foreach($currentActivityCollection as $currentActivityModel){
            $activityRecord = $currentActivityModel->toArray();
            $activityRecord['snapshot_id'] = $activityRecord['process_id'];
            unset($activityRecord['process_id']);
            unset($activityRecord['id']);
            BpmSnapshotActivity::create($activityRecord);
        }
    }

//    public function doSchedule(){
//        //获取活动的activity
//        //直接采用关联获取会不会出错
//        $currentActivityCollection = BpmActivity::where('process_id','=',$this->processModel->id)->get();
//        foreach($currentActivityCollection as $currentctivityModel){
////            变量赋值
////            $variableService = new VariableService($this->definitionFileParser, $currentctivityModel);
//
//            $activityContent = $this->definitionFileParser->findActivityContentById($currentctivityModel->activity);
//            switch($activityContent->type){
//                case 'start':
//                    //TODO:事务
//                    //新建activity，持久化
//                    $nextIds = $this->definitionFileParser->findNextActivityIdsById($currentctivityModel->activity);
//                    foreach($nextIds as $nextId){
//                        BpmActivity::create([
//                            'process_id' => $this->processModel->id,
//                            'activity' => $nextId,
//                            //TODO:时间处理
//                            'started_at' => null
//                        ]);
//                    }
//                    //删除start
//                    $currentctivityModel::delete();
//                    //快照
//                    $this->takeSnapshot();
//                    break;
//                case 'stop':
//                    //直接结束
//                    $currentctivityModel::delete();
//                    //快照
//                    $this->takeSnapshot();
//                    break;
//                case 'task':
//
//
//                    break;
//            }
//        }
//        $currentActivityCollection = BpmActivity::where('process_id','=',$this->processModel->id)->get();
//        if($currentActivityCollection !== null){
//            $this->doSchedule();
//        }
//    }

    public function initActivity($activity,$name,$description,$userId = 0){
        //task特殊判断
        if($activity === 'task'){

        }
        //持久化activity
        $activityModel = BpmActivity::create([
            'process_id' => $this->processModel->id,
            'activity' => $activity,
            //对name与description做缓存
            'name' => $name,
            'description' => $description,
            'user_id' => $userId,
            //TODO:时间处理
            'started_at' => null
        ]);
        //对数据库进行快照
        $this->takeSnapshot();
        return $activityModel->id;
    }

    public function endActivity($id){
        //TODO:是否能对find的Model做缓存，$id失效处理
        $activityModel = BpmActivity::find($id);
        $activityModel->delete();
    }

    public function doActivity($activityId){
        $activityModel = BpmActivity::find($activityId);
        $activityContent = $this->definitionFileParser->findActivityContentById($activityModel->activity);
        switch($activityContent->type){
            case 'start':
                //TODO:事务
                $nextActivities = $this->definitionFileParser->findNextActivityIdsById($activityModel->activity);
                $userId = $activityModel->user_id;
                $this->endActivity($activityId);
                foreach($nextActivities as $activity){
                    $content = $this->definitionFileParser->findActivityContentById($activity);
                    $activityId = $this->initActivity($activity,
                        property_exists($content,'name') ? $content->name : $activity,
                        property_exists($content,'description') ? $content->description : '',
                        $userId
                        );
                    $this->doActivity($activityId);
                }
                break;
            case 'stop':
                //直接结束
                $this->endActivity($activityId);
                break;
            case 'task':
                break;
        }
    }

    public static function setVariables(Array $variables,$processId){
        $processModel = BpmProcess::find($processId);
        $oldVariables = json_decode($processModel->variables,true);
        $newVariables = array_merge($oldVariables === null ? [] : $oldVariables, $variables);
        $processModel->variables = json_encode($newVariables);
        $processModel->save();
    }

    public static function completeTask($taskId, $selection, $userId, Array $variables){
        $activityModel = BpmActivity::find($taskId);
        //TODO:varibales
        ProcessService::setVariables($variables, $activityModel->process->id);
        $definition = $activityModel->process->definition;
        $service = new ProcessService($definition, $activityModel->process->id);
        $service->endActivity($taskId);
        //TODO：判定合法
        $nextActivityContent = $service->definitionFileParser->findActivityContentById($selection);
//        $activityContent->selections->$selection
        $activityId = $service->initActivity($selection,
            property_exists($nextActivityContent,'name') ? $nextActivityContent->name : $selection,
            property_exists($nextActivityContent,'description') ? $nextActivityContent->description : '',
            $userId
        );
        $service->doActivity($activityId);
    }
}

