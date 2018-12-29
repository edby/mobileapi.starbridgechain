<?php
/**
 * Created by PhpStorm.
 * User: gaoshichong
 * Date: 2018/5/15
 * Time: 11:54
 */

namespace app\wallet\controller;


use think\Db;
use think\Request;

class FeeController extends BaseController
{

    /**
     * 获取手续费
     * @param Request $request
     * @return \think\response\Json
     */
    public function getFee(Request $request)
    {
        $type   = $request->param('type','');
        $feeMap = ['btc-out','btc-in'];
        if(!in_array($type,$feeMap)){
            return jorx(['code' => 400,'msg' => '参数错误:type类型只能是btc-out,btc-in']);
        }
        $fee   = Db::table('wallet_fee')->where('fd_type',$type)->value('fd_fee') - 0;
        return jorx(['code' => 200,'msg' => '获取成功!','fee' => $fee,'name' => $fee * 1000 . '‰']);
    }


    /**
     * 获取所有手续费
     * @return \think\response\Json
     */
    public function getFeeAll()
    {
        $data = Db::table('wallet_fee')->field('fd_id as feeId,fd_fee as fee,fd_name as name')->select();
        foreach ($data as &$item){
            $item['fee'] -= 0;
        }
        return jorx(['code' => 200,'msg' => '获取成功!','data' => $data]);
    }

    /**
     * 手续费修改
     * @param Request $request
     * @return \think\response\Json
     */
    public function editFee(Request $request)
    {
        $id  = $request->param('feeId',0) - 0;
        $fee = $request->param('fee',0) - 0;
        //手续费最多10%
        if($fee < 0 || $fee > 0.1){
            return jorx(['code' => 400,'msg' => '请检查手续费是否合法']);
        }
        $data = Db::table('wallet_fee')->where('fd_id',$id)->find();
        if(!$data){
            return jorx(['code' => 400,'msg' => '信息不存在!']);
        }
        if(false !== Db::table('wallet_fee')->where('fd_id',$id)->update(['fd_fee'=> $fee])){
            return jorx(['code' => 200,'msg' => '修改成功!']);
        }
        return jorx(['code' => 400,'msg' => '修改失败，请稍后再试!']);
    }
}