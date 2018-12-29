<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/26
 * Time: 18:24
 */
namespace app\wallet\controller;

use app\wallet\model\User;
use app\wallet\service\Email;
use curl\Curl;
use redis\Redis;
use think\Db;
use think\helper\Hash;
use think\Request;

class IndexController
{
    public function index()
    {
        dump(111);
    }

    /**
     * 总余额整理脚本
     */
    public function aaa()
    {
        ini_set('memory_limit','3072M');
        set_time_limit(0);
        $user = Db::name('wallet_user')->field('fd_id')->select();
        foreach ($user as $key => $value)
        {
            $purse = Db::name('wallet_purse')->field('fd_id')->where('fd_userId' , $value['fd_id'])->select();
            $mount = 0;
            foreach ($purse as $ke => $val)
            {
                $sbc = Db::name('wkj_user_coinsbc')->where('userid' , $val['fd_id'])->find();
                $mount += $sbc['sbc'];
                $mount += $sbc['sbcd'];
            }
            Db::name('wallet_user')->where('fd_id' , $value['fd_id'])->update(['fd_sbcNums' => $mount]);
        }
    }

    public function getversion()
    {

        $info=Db::table('a_upload_config')->where(['status'=>'android'])->find();
        $result['data'] = [

            'version' => $info['version'],
            'version_code'=>$info['version_code'],
            'change_log'=>$info['change_log'],
	        'url' =>$info['url'],
            'type' => $info['type'],
        ];
        $result['status'] = 200;
        $result['msg'] = '请求成功';
        return jorx($result);
    }

    public function getios()
    {

        $info=Db::table('a_upload_config')->where(['status'=>'ios'])->find();

        $result['data'] = [

            'version' => $info['version'],
            'version_code'=>$info['version_code'],
            'change_log'=>$info['change_log'],
            'url' =>$info['url'],
            'type' => $info['type'],
            
        ];
        $result['status'] = 200;
        $result['msg'] = '请求成功';
        return jorx($result);
    }


}
