<?php
namespace PhpBpm\Dao\Impl\Eloquent
class Activity extends Eloquent {

    //不可修改
    protected $guarded = array('id');
    public $timestamps = false;

    public function user(){
        return $this->belongsTo('User');
    }
    public function process(){
        return $this->belongsTo('BpmProcess','process_id');
    }
}
