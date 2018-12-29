<?php

namespace app\wallet\model;

use think\Model;

/**
 * 用户
 * Class User
 * @package app\wallet\model
 */
class User extends Model
{
    //
    //数据表名字
    protected $table                = 'wallet_user';
    //写入时间戳
    protected $createTime           = 'fd_createDate';
    //更新时间戳
    protected $updateTime           = false;
    //自动写入时间戳格式
    protected $autoWriteTimestamp   = 'datetime';
}
