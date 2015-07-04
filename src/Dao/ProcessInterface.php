<?php

class BpmProcess extends Eloquent
{

    //不可修改
    protected $guarded = array('id');
    public $timestamps = false;

    public function definition()
    {
        return $this->belongsTo('BpmDefinition', 'definition_id');
    }

    public function launchUser(){
        return $this->belongsTo('User', 'launch_user_id');
    }


    public function activities()
    {
        return $this->hasMany('BpmActivity', 'process_id');
    }

    public function spectators()
    {
        return $this->belongsToMany('User', 'bpm_process_user', 'process_id', 'user_id');
    }


    public function getVariables()
    {
        //检查variable_filename字段，如果为null，直接返回空数组，如果非空，打开对应文件，返回variables数组
        if ($this->variable_filename !== null) {
            $variableFilePath = storage_path('bpm/variables/' . $this->variable_filename);
            //TODO:编码处理
            if ($variables = json_decode(file_get_contents($variableFilePath)) !== null) {
                //TODO:格式检查，json_schema
                //TODO:可读性检查
                //TODO:默认设置null
                if (is_array($variables)) {
                    return $variables;
                } else {
                    //TODO:抛出异常
                }
            } else {
                //TODO:抛出异常,读取文件错误，json解析错误
            }
        }
        return [];
    }

//    public function getVariables(){
//        //检查variable_filename字段，如果为null，直接返回空数组，如果非空，打开对应文件，返回variables数组
//        if($this->variable_filename !== null){
//            $variableFilePath = storage_path('bpm/variables/'.$this->variable_filename);
//            //TODO:编码处理
//            if($variables = json_decode(file_get_contents($variableFilePath)) !== null){
//                //TODO:格式检查，json_schema
//                //TODO:可读性检查
//                //TODO:默认设置null
//                if(is_array($variables)){
//                    return $variables;
//                }else{
//                    //TODO:抛出异常
//                }
//            }else{
//                //TODO:抛出异常,读取文件错误，json解析错误
//            }
//        }
//        return [];
//    }
}
