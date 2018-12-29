<?php

namespace app\wallet\model;

use think\Model;

/**
 * 路由器提现记录
 * Class Recharge
 * @package app\wallet\model
 */
class Recharge extends Model
{
    //wallet_recharge
    protected $table                = 'wallet_recharge';
    //写入时间戳
    protected $createTime           = 'fd_createDate';
    //更新时间戳
    protected $updateTime           = false;
    //自动写入时间戳格式
    protected $autoWriteTimestamp   = 'datetime';
}
