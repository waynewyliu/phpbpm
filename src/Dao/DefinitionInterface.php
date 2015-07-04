<?php

class BpmDefinition extends Eloquent {

    //不可修改
    protected $guarded = array('id');

    public function processes()
    {
        return $this->hasMany('BpmProcess', 'definition_id');
    }
}
