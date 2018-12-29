<?php
/**
 * Created by PhpStorm.
 * User: gaoshichong
 * Date: 2018/5/16
 * Time: 14:43
 */

namespace app\wallet\controller;


use think\Controller;
use think\Db;
use think\Request;

class ConfigController extends Controller
{
    /**
     * 获取官方钱包地址
     * @return \think\response\Json
     */
    public function getWalletAdr()
    {
        return jorx(['code' => 200,'msg' => '获取成功!','data' => Db::table('wallet_config')->where('fd_type','walletAdr')->field('fd_type as type,fd_key as value,fd_name as name')->find()]);
    }

    /**
     * 获取全部配置
     * @return \think\response\Json
     */
    public function getConfig()
    {
        return jorx(['code' => 200,'msg' => '获取成功!','data' => Db::table('wallet_config')->field('fd_id as configId,fd_type as type,fd_key as value,fd_name as name')->select()]);
    }

    /**
     * 修改配置
     * @param Request $request
     * @return \think\response\Json
     */
    public function editConfig(Request $request)
    {
        $configId = $request->param('configId',0) - 0;
        $key      = $request->param('value','');
        if('' == $key){
            return jorx(['code' => 400,'msg' => '配置值不能为空!']);
        }
        $data = Db::table('wallet_config')->where('fd_id',$configId)->find();
        if(!$data){
            return jorx(['code' => 400,'msg' => '配置不存在!']);
        }
        if(false !== Db::table('wallet_config')->where('fd_id',$configId)->update(['fd_key' => $key])){
            return jorx(['code' => 200,'msg' => '修改成功!']);
        }
        return jorx(['code' => 400,'msg' => '修改失败，请稍后再试!']);
    }
}