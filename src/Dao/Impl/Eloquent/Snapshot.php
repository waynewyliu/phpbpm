<?php

class BpmSnapshot extends Eloquent {

    //不可修改
    protected $guarded = array('id');
    public $timestamps = false;

}
