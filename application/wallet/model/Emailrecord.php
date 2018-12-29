<?php

namespace app\wallet\model;

use think\Model;

/**
 * 邮件发送记录
 * Class Emailrecord
 * @package app\wallet\model
 */
class Emailrecord extends Model
{
    //
    //数据表名字
    protected $table                = 'wallet_emailrecord';
    //写入时间戳
    protected $createTime           = 'fd_sendTime';
    //更新时间戳
    protected $updateTime           = false;
    //自动写入时间戳格式
    protected $autoWriteTimestamp   = 'datetime';
}
