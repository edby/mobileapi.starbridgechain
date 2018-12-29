<?php
/**
 * Created by PhpStorm.
 * User: gaoshichong
 * Date: 2018/5/15
 * Time: 12:01
 */

namespace app\wallet\controller;


use curl\Curl;
use think\Db;
use think\Request;

class ScriptController extends BaseController
{

    /**
     * 同步SBC价格
     * @param Request $request
     */
    public function synchronization(Request $request)
    {
        if(!$request->isCli()){
            exit('error');
        }
        $url      = "http://webmarket.starbridgechain.com/Ajax/getJsonTop?market=sbc_btc&t=" . mt_rand(1,99999999999);
        $data     = json_decode(Curl::get($url),true);
        $newPrice = $data['info']['new_price'];
        if(0 != $newPrice){
            $sbcPrice = 1 / $newPrice;
            //同步库
            Db::table('wallet_sbc_price')->where('fd_type','fbtc')->setField('fd_price',$sbcPrice);
        }
    }
}