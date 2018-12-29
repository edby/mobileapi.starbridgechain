<?php

namespace app\wallet\behavior;

use redis\Redis;
use think\Db;

/**
 * 登录态控制
 * Class Login
 * @package app\admin\behavior
 */
class Login
{
    /**
     * @param $params
     */
    public function run(&$params)
    {
        $request    = request();
        $rule       = strtolower($request->module() . '/' . $request->controller() . '/' . $request->action());
        $filter     = config('login.filter');
        //returnType判断
        $returnTypeMap  = ['xml','json'];
        $returnType     = $request->param('returnType','json');
        if(!in_array($returnType,$returnTypeMap)){
            abort(json(['code' => 400,'msg' => 'returnType参数错误，只能是xml或json!']));
        }
        $request->returnType  = $returnType;//返回类型
        if(in_array($rule,$filter)){
            return ;
        }
        //header头参数方式
        $token = $request->header('token','');
        //普通参数方式
        if('' == $token){
            $token = $request->param('token','');
        }
        //普通参数和header头都没有
        if('' == $token){
            abort(jorx(['code' => 400,'msg' => '未登录!']));
        }
        //token合法性
        $prefix     = config('redis.prefix');
        $redis      = Redis::instance();
        $token_key  = $prefix . 'token:' . $token;
        $admin      = $redis->get($token_key);
        if(false == $admin){
            abort(jorx(['code' => 401,'msg' => '未登录!']));
        }
        $user   = json_decode($admin,true);
        $status = Db::name('wallet_user')->where('fd_id',$user['fd_id'])->value('fd_status');
        if($status == 1)
        {
            abort(jorx(['code' => 400,'msg' => '当前账号已锁定！']));
        }
        //刷新有效期 / 30天小时
        $redis->expire($token_key ,2592000);
        //绑定请求对象
        $request->user        = $user;
        $request->accessToken = $token;
    }
}