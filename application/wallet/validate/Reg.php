<?php

namespace app\wallet\validate;
use think\Validate;

/**
 * 通知公告验证器
 * Class Notice
 * @package app\admin\validateCode
 */
class Reg extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule =   [
        'email'          => 'require|email',
        'password'       => 'require|length:8,20',
        'repassword'     => 'require|confirm:password',
    ];

    /**
     * 错误提示信息
     * @var array
     */
    protected $message  =   [
        'email.require'             => '邮箱不能为空!',
        'email.email'               => '错误的邮箱!',
        'password.require'          => '密码不能为空!',
        'password.length'           => '密码长度必须在8-20位之间!',
        'repassword'                => '两次输入的密码不一致!',

    ];
}