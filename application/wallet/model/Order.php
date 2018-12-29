<?php

namespace app\wallet\model;

use think\Model;

class Order extends Model
{
    //
    protected $table                = 'wallet_order';
    //写入时间戳
    protected $createTime           = 'fd_createDate';
    //更新时间戳
    protected $updateTime           = false;
    //自动写入时间戳格式
    protected $autoWriteTimestamp   = 'datetime';
}
