<?php


namespace app\wallet\controller;


use app\wallet\model\Purse;
use think\Db;
use think\Request;

class TradeController extends BaseController
{

    /**
     * 获取SBC价格
     * @param Request $request
     * @return \think\response\Json
     */
    public function getSBCPrice(Request $request)
    {
        $coin_type = ['cny','fbtc'];
        $type       = $request->param('type','');
        if(!in_array($type,$coin_type)){
            return jorx(['code' => 400,'msg' => '币种错误，只能是cny和fbtc']);
        }
        $price = Db::table('wallet_sbc_price')->where('fd_type',$type)->value('fd_price');
        return jorx(['code'=> 200,'msg' => '获取成功!','price' => $price]);
    }

    /**
     * cny或fbtc购买SBC
     * @param Request $request
     * @return \think\response\Json
     */
    public function buySBC(Request $request)
    {
        $coin_type  = ['cny','fbtc'];
        $user       = $request->user;
        $type       = $request->param('type','');
        if(!in_array($type,$coin_type)){
            return jorx(['code' => 400,'msg' => '币种错误，只能是cny和fbtc']);
        }
        //充值金额
        $rechargeNum      = floatval($request->param('rechargeNum',''));
        if($rechargeNum <= 0){
            return jorx(['code' => 400,'msg' => '充值金额不能是0！']);
        }
        $purseId    = $request->param('purseId',0) - 0;
        $purseAdr   = Db::table('wallet_purse')->where('fd_id',$purseId)->where('fd_userId',$user['fd_id'])->value('fd_walletAdr');
        if(!$purseAdr){
            return jorx(['code' => 400,'msg' => '钱包不存在!']);
        }
        //sbc价格
        $price = Db::table('wallet_sbc_price')->where('fd_type',$type)->value('fd_price');
        //需要花费cny或fbtc的数量
        $num      = $rechargeNum / $price;
        //cny或fbtc总额
        $totalNum =  $balance = Db::table('wkj_user_coin' . $type)->where('userid','cny' . $user['fd_id'])->value($type);
        if($totalNum <= $num){
            return jorx(['code' => 400,'msg' => $type . '余额不足！']);
        }
        Db::startTrans();
        try{
            //交易市场原状态
            $coinsbc = Db::table('wkj_user_coinsbc')->where('userid',$purseId)->find();
            if(!$coinsbc){
                $coinsbc['sbc'] =  $coinsbc['sbcd'] = 0;
                Db::table('wkj_user_coinsbc')->insert([
                    'userid' => $purseId,
                    'sbc'    => 0,
                ]);
            }
            //cny获fbtc原状态
            $cointype = Db::table('wkj_user_coin' . $type)->where('userid','cny' . $user['fd_id'])->find();
            if(!$cointype){
               $cointype[$type] =  $cointype[$type . 'd'] = 0;
                Db::table('wkj_user_coin' . $type)->insert([
                    'userid'  => 'cny' . $user['fd_id'],
                     $type    => 0,
                ]);
            }
            //更新交易市场
            Db::table('wkj_user_coinsbc')->where('userid',$purseId)->setInc('sbc',$rechargeNum);
            $sbc = Db::name('wallet_user')->where('fd_id' , $user['fd_id'])->value('fd_sbcNums');
            $sbc = bcadd($sbc , $rechargeNum , 10);
            Db::name('wallet_user')->where('fd_id' ,  $user['fd_id'])->setField('fd_sbcNums' , $sbc);
            //更新cny或fbtc余额
            Db::table('wkj_user_coin' . $type)->where('userid','cny' . $user['fd_id'])->setDec($type,$num);
            //历史记录
            $history = [
                'userid'   => $purseId,
                'coinname' => 'sbc',
                'num_a'    => $coinsbc['sbc'],
                'num_b'    => $coinsbc['sbcd'],
                'num'      => $coinsbc['sbc'] + $coinsbc['sbcd'],
                'fee'      => $rechargeNum,
                'type'     => 1,
                'name'     => 'mycz',
                'nameid'   => $purseId,
                'mum_a'    => $coinsbc['sbc'] + $rechargeNum,
                'mum_b'    => $coinsbc['sbcd'],
                'mum'      => $coinsbc['sbc'] + $rechargeNum + $coinsbc['sbcd'],
                'remark'   => '财产转移-用' . $type . '以价格' . $price . '充值' .$rechargeNum . '个sbc',
                'addtime'  => time(),
                'status'   => 1,
            ];
            //SBC日志
            Db::table('wkj_finance_sbc')->insert($history);
            //cny或fbtc日志
            $history = [
                'userid'   => 'cny' . $user['fd_id'],
                'coinname' => $type,
                'num_a'    => $cointype[$type],
                'num_b'    => $cointype[$type . 'd'],
                'num'      => $cointype[$type] + $cointype[$type . 'd'],
                'fee'      => $num,
                'type'     => 2,
                'name'     => 'mytx',
                'nameid'   => 'cny' . $user['fd_id'],
                'mum_a'    => $cointype[$type] - $num,
                'mum_b'    => $cointype[$type . 'd'],
                'mum'      => $cointype[$type] + $cointype[$type . 'd'] - $num,
                'remark'   => '财产转移-以价格' . $price . '充值' .$rechargeNum . '个sbc',
                'addtime'  => time(),
                'status'   => 1,
            ];
            Db::table('wkj_finance_' . $type)->insert($history);
            //写入订单
            Db::table('wallet_order')->insert([
                'fd_userId'         => $user['fd_id'],
                'fd_walletId'       => $purseId,
                'fd_type'           => 'in',
                'fd_sbcPrice'       => $price,
                'fd_from'           => $type,
                'fd_fromNums'       => $num,
                'fd_fromAccount'    => $user['fd_userName'],
                'fd_gone'           => 'sbc',
                'fd_goneNums'       => $rechargeNum,
                'fd_goneAccount'    => $purseAdr,
                'fd_fee'            => 0,
                'fd_real_goneNums'  => $num,
                'fd_status'         => 0,
                'fd_orderNo'        =>createOrderNo(),
                'fd_createDate'     => date('Y-m-d H:i:s'),
                'fd_updateDate'     => date('Y-m-d H:i:s'),
                'fd_operator'       => '系统',
                'fd_pictureAdr'     => '',
            ]);
            // 提交事务
            Db::commit();
            return jorx(['code' => 200,'msg' => '充值成功!']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return jorx(['code' => 400,'msg' => '购买失败，请稍后再试!']);
        }
    }

    /**
     * 充值CNY
     * @param Request $request
     * @return \think\response\Json
     */
    public function buyCNY(Request $request)
    {
        $user               = $request->user;
        $rechargeType       = $request->param('rechargeType','');
        $num                = floatval($request->param('num',0));
        $account            = $request->param('account','');
        $screenshot         = $request->param('screenshot','');
        $type_map = ['bankcard' , 'wechat'];
        if(!in_array($rechargeType,$type_map)){
            return jorx(['code' => 400,'msg' => '充值方式参数错误!只能是bankcard或wechat']);
        }
        if($num <= 0){
            return jorx(['code' => 400,'msg' => '充值金额不能为0!']);
        }
        if(!$account){
            return jorx(['code' => 400,'msg' => '充值账号不能为空!']);
        }
        if(!$screenshot){
            return jorx(['code' => 400,'msg' => '截图不能为空!']);
        }
        //订单
        //写入订单
        // 启动事务
        Db::startTrans();
        try{
            Db::table('wallet_order')->insert([
                'fd_userId'         => $user['fd_id'],
                'fd_walletId'       => '',
                'fd_type'           => 'in',
                'fd_sbcPrice'       => '',
                'fd_from'           => $rechargeType,
                'fd_fromNums'       => $num,
                'fd_fromAccount'    => $account,
                'fd_gone'           => 'cny',
                'fd_goneNums'       => $num,
                'fd_goneAccount'    => $user['fd_userName'],
                'fd_fee'            => 0,
                'fd_real_goneNums'  => '',
                'fd_status'         => 1,
                'fd_orderNo'        =>createOrderNo(),
                'fd_createDate'     => date('Y-m-d H:i:s'),
                'fd_updateDate'     => date('Y-m-d H:i:s'),
                'fd_operator'       => '',
                'fd_pictureAdr'     => $screenshot,
            ]);
            // 提交事务
            Db::commit();
            return jorx(['code' => 200,'msg' => '订单提交成功！']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return jorx(['code' => 200,'msg' => '提交订单失败！请稍后再试!']);
        }

    }

    /**
     * cny提现
     * @param Request $request
     * @return \think\response\Json
     */
    public function putCNY(Request $request)
    {
        $user               = $request->user;
        $putType            = $request->param('putType','');
        $num                = floatval($request->param('num',0));
        $account            = $request->param('account','');
        $type_map = ['bankcard' , 'wechat'];
        if(!in_array($putType,$type_map)){
            return jorx(['code' => 400,'msg' => '提现方式参数错误!只能是bankcard或wechat']);
        }
        if($num < 1){
            return jorx(['code' => 400,'msg' => '提现金额最小为1!']);
        }
        if(!$account){
            return jorx(['code' => 400,'msg' => '提现账号不能为空!']);
        }
        //用户cny余额
        $cny = Db::table('wkj_user_coincny')->where('userid','cny' . $user['fd_id'])->value('cny');
        if($cny < $num){
            return jorx(['code' => 400,'msg' => '余额不足!']);
        }
        //订单
        Db::startTrans();
        try{
            //原状态
            $last = Db::table('wkj_user_coincny')->where('userid','cny' . $user['fd_id'])->find();
            //cny手续费
            $fee = Db::table('wallet_fee')->where('fd_type','cny')->value('fd_fee') - 0;
            $feeNum = 0;
            if($fee != 0){
                $feeNum = $fee * $num;
            }
            //扣款
            Db::table('wkj_user_coincny')->where('userid','cny' . $user['fd_id'])->setDec('cny',$num);
            //cny日志
            $history = [
                'userid'   => 'cny' . $user['fd_id'],
                'coinname' => 'cny',
                'num_a'    => $last['cny'],
                'num_b'    => $last['cnyd'],
                'num'      => $last['cny'] + $last['cnyd'],
                'fee'      => $num,
                'type'     => 2,
                'name'     => 'mytx',
                'nameid'   => 'cny' . $user['fd_id'],
                'mum_a'    => $last['cny'] - $num,
                'mum_b'    => $last['cnyd'],
                'mum'      => $last['cny'] + $last['cnyd'] - $num,
                'remark'   => '财产转移-提现' . $num . '个cny到' . $putType .'账号:' . $account ,
                'addtime'  => time(),
                'status'   => 1,
            ];
            Db::table('wkj_finance_cny')->insert($history);
            //订单
            Db::table('wallet_order')->insert([
                'fd_userId'         => $user['fd_id'],
                'fd_walletId'       => '',
                'fd_type'           => 'out',
                'fd_sbcPrice'       => '',
                'fd_from'           => 'cny',
                'fd_fromNums'       => $num,
                'fd_fromAccount'    => $user['fd_userName'],
                'fd_gone'           => $putType,
                'fd_goneNums'       => $num - $feeNum,
                'fd_goneAccount'    => $account,
                'fd_fee'            => $feeNum,
                'fd_real_goneNums'  => '',
                'fd_status'         => 1,
                'fd_orderNo'        =>createOrderNo(),
                'fd_createDate'     => date('Y-m-d H:i:s'),
                'fd_updateDate'     => date('Y-m-d H:i:s'),
                'fd_operator'       => '',
                'fd_pictureAdr'     => '',
            ]);
            // 提交事务
            Db::commit();
            return jorx(['code' => 200,'msg' => '订单提交成功！']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return jorx(['code' => 200,'msg' => '提交订单失败！请稍后再试!']);
        }
    }

    /**
     * fbtc充值
     * @param Request $request
     * @return \think\response\Json
     */
    public function buyFBTC(Request $request)
    {
        $user               = $request->user;
        $rechargeType       = $request->param('rechargeType','');
        $num                = floatval($request->param('num',0));
        $account            = $request->param('account','');
        $screenshot         = $request->param('screenshot','');
        $type_map = ['publicWallet'];
        if(!in_array($rechargeType,$type_map)){
            return jorx(['code' => 400,'msg' => '充值方式参数错误!只能是publicWallet']);
        }
        if($num <= 0){
            return jorx(['code' => 400,'msg' => '充值金额不能为0!']);
        }
        if(!$account){
            return jorx(['code' => 400,'msg' => '充值账号不能为空!']);
        }
        if(!$screenshot){
            return jorx(['code' => 400,'msg' => '截图不能为空!']);
        }
        //订单
        //写入订单
        // 启动事务
        Db::startTrans();
        try{
            Db::table('wallet_order')->insert([
                'fd_userId'         => $user['fd_id'],
                'fd_walletId'       => '',
                'fd_type'           => 'in',
                'fd_sbcPrice'       => '',
                'fd_from'           => $rechargeType,
                'fd_fromNums'       => $num,
                'fd_fromAccount'    => $account,
                'fd_gone'           => 'fbtc',
                'fd_goneNums'       => $num,
                'fd_goneAccount'    => $user['fd_userName'],
                'fd_fee'            => 0,
                'fd_real_goneNums'  => '',
                'fd_status'         => 1,
                'fd_orderNo'        =>createOrderNo(),
                'fd_createDate'     => date('Y-m-d H:i:s'),
                'fd_updateDate'     => date('Y-m-d H:i:s'),
                'fd_operator'       => '',
                'fd_pictureAdr'     => $screenshot,
            ]);
            // 提交事务
            Db::commit();
            return jorx(['code' => 200,'msg' => '订单提交成功！']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return jorx(['code' => 200,'msg' => '提交订单失败！请稍后再试!']);
        }

    }

    /**
     * fbtc提现
     * @param Request $request
     * @return \think\response\Json
     */
    public function putFBTC(Request $request)
    {
        $user               = $request->user;
        $putType            = $request->param('putType','');
        $num                = floatval($request->param('num',0));
        $account            = $request->param('account','');
        $type_map = ['publicWallet'];
        if(!in_array($putType,$type_map)){
            return jorx(['code' => 400,'msg' => '提现方式参数错误!只能是publicWallet']);
        }
        if($num <= 0){
            return jorx(['code' => 400,'msg' => '提现金额不能为0!']);
        }
        if(!$account){
            return jorx(['code' => 400,'msg' => '提现账号不能为空!']);
        }
        //用户fbtc余额
        $fbtc = Db::table('wkj_user_coinfbtc')->where('userid','cny' . $user['fd_id'])->value('fbtc');
        if($fbtc < $num){
            return jorx(['code' => 400,'msg' => '余额不足!']);
        }
        //订单
        Db::startTrans();
        try{
            //原状态
            $last = Db::table('wkj_user_coinfbtc')->where('userid','cny' . $user['fd_id'])->find();
            //fbtc手续费
            $fee = Db::table('wallet_fee')->where('fd_type','fbtc')->value('fd_fee') - 0;
            $feeNum = 0;
            if($fee != 0){
                $feeNum = $fee * $num;
            }
            //扣款
            Db::table('wkj_user_coinfbtc')->where('userid','cny' . $user['fd_id'])->setDec('fbtc',$num);
            //cny日志
            $history = [
                'userid'   => 'cny' . $user['fd_id'],
                'coinname' => 'fbtc',
                'num_a'    => $last['fbtc'],
                'num_b'    => $last['fbtcd'],
                'num'      => $last['fbtc'] + $last['fbtcd'],
                'fee'      => $num,
                'type'     => 2,
                'name'     => 'mytx',
                'nameid'   => 'cny' . $user['fd_id'],
                'mum_a'    => $last['fbtc'] - $num,
                'mum_b'    => $last['fbtcd'],
                'mum'      => $last['fbtcd'] + $last['fbtc'] - $num,
                'remark'   => '财产转移-提现' . $num . '个fbtc到' . $putType .'账号:' . $account ,
                'addtime'  => time(),
                'status'   => 1,
            ];
            Db::table('wkj_finance_fbtc')->insert($history);

            Db::table('wallet_order')->insert([
                'fd_userId'         => $user['fd_id'],
                'fd_walletId'       => '',
                'fd_type'           => 'out',
                'fd_sbcPrice'       => '',
                'fd_from'           => 'fbtc',
                'fd_fromNums'       => $num,
                'fd_fromAccount'    => $user['fd_userName'],
                'fd_gone'           => $putType,
                'fd_goneNums'       => $num - $feeNum,
                'fd_goneAccount'    => $account,
                'fd_fee'            => $feeNum,
                'fd_real_goneNums'  => '',
                'fd_status'         => 1,
                'fd_orderNo'        =>createOrderNo(),
                'fd_createDate'     => date('Y-m-d H:i:s'),
                'fd_updateDate'     => date('Y-m-d H:i:s'),
                'fd_operator'       => '',
                'fd_pictureAdr'     => '',
            ]);
            // 提交事务
            Db::commit();
            return jorx(['code' => 200,'msg' => '订单提交成功！']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return jorx(['code' => 200,'msg' => '提交订单失败！请稍后再试!']);
        }
    }
    
    /**
     * BTC提现到FBTC
     * @param Request $request
     * @return \think\response\Json
     */
    public function putBTC2FBTC(Request $request)
    {
        $user               = $request->user;
        $num                = floatval($request->param('num',0));
        if($num < 0.1){
            return jorx(['code' => 400,'msg' => '提现最小为0.1!']);
        }
        //用户btc余额
        $btc = Db::table('wkj_user_coinbtc')->where('userid', $user['fd_id'])->value('btc');
        if($btc < $num){
            return jorx(['code' => 400,'msg' => '余额不足!']);
        }
        //订单
        Db::startTrans();
        try{
            //btc手续费
            $fee = Db::table('wallet_fee')->where('fd_type','btc')->value('fd_fee') - 0;
            $feeNum = 0;
            if($fee != 0){
                $feeNum = $fee * $num;
            }
            //交易市场原状态
            $coinbtc = Db::table('wkj_user_coinbtc')->where('userid',$user['fd_id'])->find();
            //fbtc原状态
            $coinfbtc = Db::table('wkj_user_coinfbtc')->where('userid','cny' . $user['fd_id'])->find();
            if(!$coinfbtc){
                $coinfbtc['fbtc'] =  $coinfbtc['fbtcd'] = 0;
                Db::table('wkj_user_coinfbtc')->insert([
                    'userid'  => 'cny' . $user['fd_id'],
                    'fbtc'    => 0,
                ]);
            }
            //更新交易市场
            Db::table('wkj_user_coinbtc')->where('userid',$user['fd_id'])->setDec('btc',$num);
            Db::name('btc_change')->insert([
                'user_id'=>$user['fd_id'],
                'add_btc'=>0,
                'minus_btc'=>$num,
                'type'=>5,
                'time'=>date('Y-m-d H:i:s')
            ]);

            //更新fbtc余额
            Db::table('wkj_user_coinfbtc')->where('userid','cny' . $user['fd_id'])->setInc('fbtc',$num - $feeNum);
            //历史记录
            $history = [
                'userid'   => $user['fd_id'],
                'coinname' => 'btc',
                'num_a'    => $coinbtc['btc'],
                'num_b'    => $coinbtc['btcd'],
                'num'      => $coinbtc['btc'] + $coinbtc['btcd'],
                'fee'      => $num,
                'type'     => 2,
                'name'     => 'mytx',
                'nameid'   => $user['fd_id'],
                'mum_a'    => $coinbtc['btc'] - $num,
                'mum_b'    => $coinbtc['btcd'],
                'mum'      => $coinbtc['btc'] + $coinbtc['btcd'] - $num,
                'remark'   => '财产转移-转移' . $num . '个btc到fbtc，手续费:' . $feeNum,
                'addtime'  => time(),
                'status'   => 1,
            ];
            //btc日志
            Db::table('wkj_finance_btc')->insert($history);
            //fbtc日志
            $history = [
                'userid'   => 'cny' . $user['fd_id'],
                'coinname' => 'fbtc',
                'num_a'    => $coinfbtc['fbtc'],
                'num_b'    => $coinfbtc['fbtcd'],
                'num'      => $coinfbtc['fbtc'] + $coinfbtc['fbtcd'],
                'fee'      => $num - $feeNum,
                'type'     => 1,
                'name'     => 'mycz',
                'nameid'   => 'cny' . $user['fd_id'],
                'mum_a'    => $coinfbtc['fbtc'] + $num - $feeNum,
                'mum_b'    => $coinfbtc['fbtcd'],
                'mum'      => $coinfbtc['fbtc'] + $num + $coinfbtc['fbtcd'] - $feeNum,
                'remark'   => '财产转移-转移' . $num . '个btc到fbtc,手续费:'.$feeNum,
                'addtime'  => time(),
                'status'   => 1,
            ];
            Db::table('wkj_finance_fbtc')->insert($history);

            //订单
            Db::table('wallet_order')->insert([
                'fd_userId'         => $user['fd_id'],
                'fd_walletId'       => '',
                'fd_type'           => 'out',
                'fd_sbcPrice'       => '',
                'fd_from'           => 'btc',
                'fd_fromNums'       => $num,
                'fd_fromAccount'    => $user['fd_userName'],
                'fd_gone'           => 'fbtc',
                'fd_goneNums'       => $num - $feeNum,
                'fd_goneAccount'    => $user['fd_userName'],
                'fd_fee'            => $feeNum,
                'fd_real_goneNums'  => '',
                'fd_status'         => 0,
                'fd_orderNo'        =>createOrderNo(),
                'fd_createDate'     => date('Y-m-d H:i:s'),
                'fd_updateDate'     => date('Y-m-d H:i:s'),
                'fd_operator'       => '系统',
                'fd_pictureAdr'     => '',
            ]);
            // 提交事务
            Db::commit();
            return jorx(['code' => 200,'msg' => '提现成功']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return jorx(['code' => 200,'msg' => '提现失败!请稍后再试!']);
        }
    }

    /**
     * BTC体现到公网钱包
     * @param Request $request
     * @return \think\response\Json
    */
    public function putBTC2Wallet(Request $request)
    {
        $user               = $request->user;
        $putType            = $request->param('putType','');
        $num                = floatval($request->param('num',0));
        $amount                = (float)floatval($request->param('amount',0));
        $account            = $request->param('account','');
        $type_map = ['publicWallet'];
        $fee = Db::table('wallet_fee')->where('fd_type','btc-out')->value('fd_fee') - 0;
        $new_amount = (float)$num - $fee;
        /*
         * 判断是否是 特殊账户提取 btc
         * */
        $user_info = Db::name('wallet_user')->where(['fd_id'=>$user['fd_id']])->find();
        if($user_info['fd_type']==1){      //如果是特殊用用户,查看是否到btc可提取时间和可提取交易额
            $th_months_later=date('Y-m-d H:i:s',strtotime("+3 months",strtotime($user_info['fd_createDate'])));

            if(date("Y-m-d H:i:s")<$th_months_later){      //三个月内提取

                    return jorx(['code' => 400,'msg' => '特殊用户3个月内，不可提取btc！']);

            }else{             //三个月以后提取
                $trade_num=0.00000000000000;

                $aa=Db::name('wallet_purse')->where(['fd_userId'=>$user['fd_id']])->select();
                $unlix=strtotime($user_info['fd_createDate']);
                foreach($aa as $k=>$v){
                    $temp_where['addtime']=['egt',$unlix];
                    $temp_where['type']=['eq',2];
                    $temp_where['userid']=['eq',$v['fd_id']];

                    $total = Db::name('wkj_trade_logsbc_btc')
                        ->where($temp_where)
                        ->select();

                    //获取买入交易额
                    foreach ($total as $key => $value) {
                        $trade_num+=$value['mum'];
                    }
                }

                //获取已提取btc
                $order= Db::name('wallet_order')
                    ->whereBetween('fd_createDate' , [$user_info['fd_createDate'] ,$th_months_later])
                    ->where(['fd_userId'=>$user['fd_id']])
                    ->where(['fd_status'=>0])
                    ->select();
                $put_btc_num=0.000000000000;
                foreach($order as $k=>$v){
                    $put_btc_num+=$v['fd_fromNums'];
                }

                $out_nums=floatval(bcsub($trade_num,$put_btc_num,15));

                if($num>$out_nums){
                    return jorx(['code' => 400,'msg' => '可提现数量:'.$out_nums]);
                }
            }

        }




        if(bcsub($amount,$new_amount) != 0)
        {
            return jorx(['code' => 400,'msg' => '提现金额有误！']);
        }
        if(!in_array($putType,$type_map)){
            return jorx(['code' => 400,'msg' => '提现方式参数错误!只能是publicWallet']);
        }
//        if($num <= 0){
//           return jorx(['code' => 400,'msg' => '提现金额不能为0!']);
//        }
        if($num < 0.001){
            return jorx(['code' => 400,'msg' => '提现最小为0.001!']);
        }
        if(!$account){
            return jorx(['code' => 400,'msg' => '提现账号不能为空!']);
        }
        if(strlen($account) < 26 || strlen($account) > 34){
            return jorx(['code' => 400,'msg' => '钱包地址不合法!']);
        }
        //用户btc余额
        $btc = Db::table('wkj_user_coinbtc')->where('userid', $user['fd_id'])->value('btc');
//        if($num <= 0.001)
//      {
//            return jorx(['code' => 400,'msg' => '提现金额不能小于手续费']);
//        }

        if($btc < $num){
            return jorx(['code' => 400,'msg' => '余额不足!']);
        }
        //订单
        Db::startTrans();
        try{
            //btc提现手续费
//            $fee    = Db::table('wallet_fee')->where('fd_type','btc-out')->value('fd_fee') - 0;
//            $feeNum = 0;
//            if($fee != 0){
//                $feeNum = $fee * $num;
//            }
            Db::name('wkj_user_coinbtc')->where('userId',$user['fd_id'])->setDec('btc',$num);
            $after_btc = Db::table('wkj_user_coinbtc')->where('userid', $user['fd_id'])->value('btc');
            //订单
            Db::table('wallet_order')->insert([
                'fd_userId'         => $user['fd_id'],
                'fd_walletId'       => '',
                'fd_type'           => 'out',
                'fd_sbcPrice'       => '',
                'fd_from'           => 'btc',
                'fd_fromNums'       => $num,
                'fd_fromAccount'    => $user['fd_userName'],
                'fd_gone'           => $putType,
                'fd_goneNums'       => $num - $fee,
                'fd_goneAccount'    => $account,
                'fd_fee'            => $fee,
                'fd_real_goneNums'  => '',
                'fd_status'         => 1,
                'fd_orderNo'        => createOrderNo(),
                'fd_createDate'     => date('Y-m-d H:i:s'),
                'fd_updateDate'     => date('Y-m-d H:i:s'),
                'fd_operator'       => '',
                'fd_pictureAdr'     => '',
                'fd_before_btc'     => $btc,
                'fd_after_btc'     => $after_btc,
            ]);
            // 提交事务
            Db::commit();
            return jorx(['code' => 200,'msg' => '订单提交成功！']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return jorx(['code' => 200,'msg' => '提交订单失败！请稍后再试!']);
        }
    }

    /**
     * BTC充值
     * @param Request $request
     * @return \think\response\Json
     */
    public function buyBTC(Request $request)
    {
        $user               = $request->user;
        $rechargeType       = $request->param('rechargeType','');
        $num                = floatval($request->param('num',0));
        $account            = $request->param('account','');
        $screenshot         = $request->param('screenshot','');

        $type_map = ['publicWallet'];
        if(!in_array($rechargeType,$type_map)){
            return jorx(['code' => 400,'msg' => '充值方式参数错误!只能是publicWallet']);
        }


        if(!$screenshot){
            return jorx(['code' => 400,'msg' => '截图不能为空!']);
        }
        //订单
        // 启动事务
        Db::startTrans();
        try {
            //btc充值手续费
            $fee = Db::table('wallet_fee')->where('fd_type','btc-in')->value('fd_fee') - 0;
            $feeNum = 0;
            if($fee != 0){
                $feeNum = $fee * $num;
            }
            Db::table('wallet_order')->insert([
                'fd_userId'         => $user['fd_id'],
                'fd_walletId'       => '',
                'fd_type'           => 'in',
                'fd_sbcPrice'       => '',
                'fd_from'           => $rechargeType,
                'fd_fromNums'       => $num,
                'fd_fromAccount'    => $account,
                'fd_gone'           => 'btc',
                'fd_goneNums'       => $num - $feeNum,
                'fd_goneAccount'    => $user['fd_userName'],
                'fd_fee'            => $feeNum,
                'fd_real_goneNums'  => '',
                'fd_status'         => 1,
                'fd_orderNo'        =>createOrderNo(),
                'fd_createDate'     => date('Y-m-d H:i:s'),
                'fd_updateDate'     => date('Y-m-d H:i:s'),
                'fd_operator'       => '',
                'fd_pictureAdr'     => $screenshot,
            ]);
            $order_id=Db::table('wallet_order')->getLastInsID();

            // btc地址被锁定
            $where['user_id']=$user['fd_id'];
            $where['type']=2;

            $arr['type']=3;
            $arr['num']=$num;
            $re=Db::table('wallet_bind_addr')->where($where)->find();
            if($re!=null){
                Db::table('wallet_bind_addr')->where($where)->update($arr);
                $ra=Db::table('wallet_bind_addr')->where($where)->find();
                //插入历史记录
                Db::table('wallet_bind_addr_history')->insert([
                    /*'addr_id'=>$ra['id'],*/
                    'user_id'=>$user['fd_id'],
                    'order_id'=>$order_id,
                    'create_time'=>date("Y-m-d H:i:s"),
                ]);
            }


            // 提交事务
            Db::commit();
            return jorx(['code' => 200,'msg' => '订单提交成功！']);
    } catch (\Exception $e) {
            Db::rollback();
            return jorx(['code' => 400, 'msg' => '提交订单失败！请稍后再试!', 'error' => $e->getMessage()]);
        }
    }

    /**
     * 计算btc提现后的金额
     * @return \think\response\Json
     */
    public function getBalance()
    {
        $data = Request::instance()->post();
        $fee = Db::table('wallet_fee')->where('fd_type','btc-out')->value('fd_fee') - 0;
        if(array_key_exists('amount' , $data) && $data['amount'] > $fee)
        {
            $amount = $data['amount'] - $fee;
            return jorx(['code' => 200, 'amount' => (string)$amount , 'msg' => '计算成功!']);
        }
        else
        {
            return jorx(['code' => 201,'msg' => '需要计算的金额不正确!']);
        }
    }
}