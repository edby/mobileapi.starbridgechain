<?php

namespace app\wallet\validate;
use think\Validate;

/**
 * 通知公告验证器
 * Class Notice
 * @package app\admin\validateCode
 */
class Login extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule =   [
        'username'       => 'require|email',
        'password'       => 'require|length:8,20',
    ];

    /**
     * 错误提示信息
     * @var array
     */
    protected $message  =   [
        'email.require'             => '邮箱不能为空!',
        'email.email'               => '错误的邮箱!',
        'password.require'          => '密码不能为空!',
        'password.length'           => '密码格式错误!',
    ];
}