<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/15
 * Time: 16:03
 */

return [
    'success'       =>      ['status'=>200,'msg'=>'操作成功'],
    'error'         =>      ['status'=>201,'msg'=>'操作失败'],
    'null'          =>      ['status'=>202,'msg'=>'暂无数据'],
    'mistake'       =>      ['status'=>203,'msg'=>'参数错误'],
    'lose'          =>      ['status'=>204,'msg'=>'访问失败'],
    'logout'        =>      ['status'=>205,'msg'=>'登录失效'],
    'uploadsuccess' =>      ['status'=>204,'msg'=>'添加成功'],
    'uploaderror'   =>      ['status'=>205,'msg'=>'添加错误'],
    'delsuccess'    =>      ['status'=>206,'msg'=>'删除成功'],
    'miss'  		=>      ['status'=>207,'msg'=>'缺少参数'],
    'editsuccess'   =>      ['status'=>208,'msg'=>'更新成功'],
    'editerror'     =>      ['status'=>209,'msg'=>'更新失败'],
    'tel'           =>      ['status'=>211,'msg'=>'该号码已注册'],
    'register'      =>      ['status'=>212,'msg'=>'获取验证码失败,该手机号还未注册！'],
    'regin'         =>      ['status'=>213,'msg'=>'该手机号已经注册，请选择登录！'],
    'telIsReg'      =>      ['status'=>214,'msg'=>'该号码已注册，请绑定其他手机号'],
    'notreg'        =>      ['status'=>215,'msg'=>'该手机号还未注册！'],
    'postcode'      =>      ['status'=>216,'msg'=>'你的邮编不正确！'],
    'misssupplier'  =>      ['status'=>217,'msg'=>'商户登录失效！'],
    'pwdFail'       =>      ['status'=>218,'msg'=>'账号或密码错误！'],
    'telloss'       =>      ['status'=>219,'msg'=>'该账号不存在'],
    'codeFail'      =>      ['status'=>220,'msg'=>'验证码错误'],
    'cpNoMate'      =>      ['status'=>310,'msg'=>'账号密码不匹配！'],
    'tokenfalse'    =>      ['status'=>300,'msg'=>'TOKEN验证失败！'],
    'logfail'       =>      ['status'=>301,'msg'=>'登录失败，密码不正确'],
    'uploadCertificate'       =>      ['status'=>302,'msg'=>'操作失败，请上传支付凭证'],
];