<?php

namespace app\wallet\model;

use think\Model;

class Notice extends Model
{
    protected $table = "star_bridge_notice";

    protected $autoWriteTimestamp = true; //开启自动写入时间戳字段 'datetime';(时间字符串)

    protected $createTime = 'createDate';

    protected $updateTime = 'updateDate';

//    use SoftDelete;
//    protected $deleteTime = 'delete_time';

    //关联
//    public function logo()
//    {
//        return $this->belongsTo('GoodsPics','id','itemId');  //关联的第一个参数必须是模型名字
//    }
}
