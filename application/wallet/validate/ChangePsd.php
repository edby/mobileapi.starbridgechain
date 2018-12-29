<?php
/**
 * Created by PhpStorm.
 * User: gaoshichong
 * Date: 2018/4/28
 * Time: 12:25
 */

namespace app\wallet\validate;


use think\Validate;

class ChangePsd extends Validate
{
    /**
     * 验证规则
     * @var array
     */
    protected $rule =   [
        'prePassword'         => 'require|length:8,20',
        'newPassword'         => 'require|length:8,20',
        'reNewPassword'       => 'require|confirm:newPassword|different:prePassword',
    ];

    /**
     * 错误提示信息
     * @var array
     */
    protected $message  =   [
        'prePassword'               => '原密码格式错误!',
        'newPassword'               => '新密码格式错误!',
        'reNewPassword.require'     => '两次新密码不一致!',
        'reNewPassword.confirm'     => '两次新密码不一致!',
        'reNewPassword.different'   => '不能和原密码相同!',
    ];
}