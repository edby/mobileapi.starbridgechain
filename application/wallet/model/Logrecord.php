<?php

namespace app\wallet\model;

use think\Model;

/**
 * 登录日志
 * Class Logrecord
 * @package app\wallet\model
 */
class Logrecord extends Model
{
    //fd_logrecord
    //
    protected $table                = 'fd_logrecord';
    //写入时间戳
    protected $createTime           = 'fd_createDate';
    //更新时间戳
    protected $updateTime           = false;
    //自动写入时间戳格式
    protected $autoWriteTimestamp   = 'datetime';
}
