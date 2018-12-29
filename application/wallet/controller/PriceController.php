<?php
/**
 * Created by PhpStorm.
 * User: gaoshichong
 * Date: 2018/5/14
 * Time: 10:49
 */

namespace app\wallet\controller;


use think\Db;
use think\Request;

class PriceController extends BaseController
{

    /**
     * 获取sbc价格
     * @return \think\response\Json
     */
    public function getList()
    {
        return jorx(['code' => 200,'msg' => '获取成功','data' => Db::table('wallet_sbc_price')->field('fd_createDate,fd_updateDate',true)->select()]);
    }


    /**
     * 修改sbc价格
     * @param Request $request
     * @return \think\response\Json
     */
    public function editPrice(Request $request)
    {
        $price = $request->param('price',0) - 0;
        if(0 == $price){
            return jorx(['code' => 400,'msg' => '价格不能为0!']);
        }
        $priceId = $request->param('priceId',0) - 0;
        $data    = Db::table('wallet_sbc_price')->where('fd_id',$priceId)->find();
        if(!$data){
            return jorx(['code' => 400,'msg' => '没有该类型价格!']);
        }
        if(false !== Db::table('wallet_sbc_price')->where('fd_id',$priceId)->setField('fd_price',$price)){
            return jorx(['code' => 200,'msg' => '修改成功!']);
        }
        return jorx(['code' => 400,'msg' => '修改失败，请稍后再试!']);
    }
}