<?php
//邮件

/**
 * 邮箱类型：POP3
账号：admin@starbridgechain.com
收件服务器：mail.starbridgechain.com 端口：110
发件服务器：mail.starbridgechain.com 端口：25
密码：AUX_gehua@123!@
 */

/**
 *   //邮件
'email'                   => [
'username'     => 'noreply@shoplist.cn',
'nickname'     => 'xxx',
'password'     => 'Zhongzai123.',
//        'host'         => 'smtp.exmail.qq.com',//qq
'host'         => 'smtp.mxhichina.com',
'port'         => '465',
'security'     => 'ssl'
],
 */
return   [
    'username'     => 'admin@mail.starbridgechain.com',
    'nickname'     => 'SDT<starbridgechain.io>',
    'password'     => 'AUXgehua123',
    'host'         => 'smtpdm.aliyun.com',
    'port'         => '465',
    'title'        => 'SDT  verification code',
    'verification' => '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>starbridgechain</title>
</head>
<body>
<p>Welcome to starbridgechain.io</p>
<p>Your verification code is</p>
<h3 style="color:  red">{{code}}</h3>
<p>If you did not submit this request, please ensure your account has not been compromised.</p>
<p>For safety reasons, the effective time is 30 minutes.</p>
<p>Regards</p>
<p>http://starbridgechain.io/ Team</p>
</body>
</html>',
    'security'     => 'ssl'
];




//return   [
//    'username'     => 'starbridgechain@gmail.com',
//    'nickname'     => 'SDT<starbridgechain.io>',
//    'password'     => 'AUXgehua123',
//    'host'         => 'smtpdm.aliyun.com',
//    'port'         => '25',
//    'title'        => 'SDT  verification code',
////    'security'     => 'ssl',
//    'verification' => '<!DOCTYPE html>
//<html lang="en">
//<head>
//    <meta charset="UTF-8">
//    <title>starbridgechain</title>
//</head>
//<body>
//<p>Welcome to starbridgechain.io</p>
//<p>Your verification code is</p>
//<h3 style="color:  red">{{code}}</h3>
//<p>If you did not submit this request, please ensure your account has not been compromised.</p>
//<p>For safety reasons, the effective time is 30 minutes.</p>
//<p>Regards</p>
//<p>http://starbridgechain.io/ Team</p>
//</body>
//</html>',
//    'security'     => false
//];


