<?php
/**
 * Created by PhpStorm.
 * User: dafo
 * Date: 2015/5/7
 * Time: 0:42
 */

class VariableService {
    protected $fileDir = 'bpm/variables/';
    protected $fileName;
    protected $definitionFileParser;
    protected $activityModel;

    function __construct($definitionFileParser, $activityModel){
        $this->fileName = $activityModel->activity.$activityModel->id;
        touch($this->fileDir.$this->fileName,'a+') ? true : false;//TODO:失败抛出异常
        $this->definitionFileParser = $definitionFileParser;
        $this->activityModel = $activityModel;
    }

    public function getVariableFileName(){
        return $this->fileName;
    }

}