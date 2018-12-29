<?php


namespace app\wallet\controller;


use app\wallet\model\Purse;
use app\wallet\model\User;
use curl\Curl;
use think\Db;
use think\helper\Hash;
use think\helper\Str;
use think\Request;

class RouteController extends BaseController
{
    //验证路由tel和uuid
    private static $Check_RouterMobileBinding = 'https://ApiPortal.cmbcrouter.com:8016/api/AppWebApi/Check_RouterMobileBinding';
    //提现
    private static $CashRedemption            = 'http://coin.starbridgechain.com:8080/api/SBCServices/CashRedemption';
    //提现失败回滚
    private static $WithdrawalRollBack        = 'http://coin.starbridgechain.com:8080/api/SBCServices/WithdrawalRollBack';

    /**
     * 绑定路由app账号
     * @param Request $request
     * @param User $userModel
     * @return \think\response\Json
     */
    public function bindUser(Request $request,User $userModel)
    {
        $username = $request->param('username','');
        $password = $request->param('password','');
        $rtuserId = $request->param('rtuserId','');
        if(strlen($rtuserId) !== 11){
            return jorx(['code' => 400,'msg' => '路由appid错误!']);
        }
        $user = $userModel->where('fd_email',$username)->find();
        if(!$user){
           return jorx(['code' => 400,'msg' => '用户名或密码错误!']);
        }
        if(!Hash::check($password,$user['fd_psd'],'md5',['salt' => $user['fd_salt']])){
            return jorx(['code' => 400,'msg' => '用户名或密码错误!']);
        }
        //绑定
        if(Db::table('rtuser_walletuser')->where('fd_rtuserId',$rtuserId)->where('fd_wtuserId',$user['fd_id'])->find()){
            return jorx(['code' => 400,'msg' => '该路由账号和钱包账号已绑定!']);
        }
        $result = Db::table('rtuser_walletuser')->insert([
            'fd_rtuserId'   => $rtuserId,
            'fd_wtuserId'   => $user['fd_id'],
            'fd_createDate' => date('Y-m-d H:i:s'),
        ]);
        if($result){
            return jorx(['code' => 200,'msg' => '绑定成功!']);
        }
        return jorx(['code' => 400,'msg' => '绑定失败，请稍后再试!']);
    }


    /**
     * 路由绑定钱包
     * @param Request $request
     * @param User $userModel
     * @param Purse $purse
     * @return array|\think\response\Json
     */
    public function bindWallet(Request $request, User $userModel,Purse $purse)
    {
        $routerTel  = $request->param('routerTel','');
        $routerUUid = $request->param('routerUUid','');
        $mobile     = Str::random(12);
        //验证路由tel和uuid
        $temp       = [
            'Target_Mobile'         => $routerTel,
            'Target_RouterUUID'     => $routerUUid,
            'AppKey'                => strtoupper(md5($mobile . date('Ymd'))),
            'Mobile'                => $mobile
        ];
        $rst        = Curl::post(self::$Check_RouterMobileBinding,array2urlParams($temp));
        $rst = json_decode($rst,true);
        if(empty($rst))
        {
            return jorx(['code' => 400,'msg' => '验证不通过']);
        }
        if($rst['Header']['Msg'] !== 'ok'){
            return jorx(['code' => 400,'msg' => $rst['Header']['Msg']]);
        }
        $rtuserId = $request->param('email','');
        $user     = $userModel->where('fd_email',$rtuserId)->find();
        if(!$user){
            return jorx(['code' => 400,'msg' => '钱包app用户不存在!']);
        }
        //是否已经绑定
        $wallet_res = Db::table('rt_wallet')->where('fd_routerTel',$routerTel)->where('fd_routerUUid',$routerUUid)->where('fd_status',1)->find();
        if( $wallet_res ){
            return jorx(['code' => 400,'msg' => '该路由器已有绑定钱包，请解绑后再绑定!' , 'wallet_id' => $wallet_res['fd_walltId']]);
        }
        // 启动事务
        Db::startTrans();
        try{
            $temp = Db::table('rt_wallet')->where('fd_routerTel',$routerTel)->where('fd_routerUUid',$routerUUid)->where('fd_walletUserId',$user['fd_id'])->lock(true)->find();
            if($temp){
                Db::table('rt_wallet')->where('fd_routerTel',$routerTel)->where('fd_routerUUid',$routerUUid)->where('fd_walletUserId',$user['fd_id'])->update([
                    'fd_status' => 1
                ]);
                //有历史绑定，直接修改状态返回
                return jorx(['code' => 200,'msg' => '绑定成功!','purse' => [
                    'purseId'   => $temp['fd_walltId'],
                ]]);
            }
            $purseAddress = createPurse();
            //创建钱包
            $purse->data([
                'fd_userId'         => $user['fd_id'],
                'fd_walletAdr'      => $purseAddress,
                'fd_psd'            => $user['fd_psd'],
                'fd_salt'           => $user['fd_salt'],
                'fd_status'         => 1,
                'fd_isRelieve'      => 1,
            ])->save();
            $purseId = $purse->getLastInsID();
            //路由器--钱包绑定关联
            Db::table('rt_wallet')->insert([
                'fd_routerTel'          => $routerTel,
                'fd_routerUUid'         => $routerUUid,
                'fd_walletUserId'       => $user['fd_id'],
                'fd_walltId'            => $purseId,
                'fd_walletAdr'          => $purseAddress,
            ]);
            //btc账户和钱包
            Db::table('wallet_purse_BBTC')->insert([
                'fd_walletId'   => $purseId,
                'fd_wtuserId'   => $user['fd_id'],
                'fd_createDate' => date('Y-m-d H:i:s'),
            ]);
            //交易市场数据库插入
            Db::table('wkj_user')->insert([
                'id'          => $purseId,
                'username'    => $purseAddress,
                'payencrypt'  => $user['fd_salt'],
                'paypassword' => $user['fd_psd'],
                'status'      => 1,
            ]);
            //初始化余额
            Db::table('wkj_user_coinsbc')->insert([
                'userid' => $purseId
            ]);
            // 提交事务
            Db::commit();
            return jorx(['code' => 200,'msg' => '绑定成功!','purse' => [
                'purseId'   => $purseId,
            ]]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return jorx(['code' => 400,'msg' => '绑定异常，请稍后再试!']);
        }
    }

    /**
     * 提现
     * @param Request $request
     * @param User $user
     * @return \think\response\Json
     */
    public function putSBC(Request $request,User $user)
    {

        $user_id = $request->user['fd_id'];

        //钱包地址
        $purseId  = $request->param('purseId','') - 0;
        //查询uuid、钱包id、用户id
        $data  = Db::table('rt_wallet')->where('fd_walltId',$purseId)->field('fd_walletUserId,fd_routerUUid,fd_walltId,fd_walletAdr')->find();
        if(!$data){
            return jorx(['code' => 400,'msg' => '钱包不存在或未绑定!']);
        }

        $routeUUID = $data['fd_routerUUid'];
        $emailAddr = $user->where('fd_id',$data['fd_walletUserId'])->value('fd_email');
        $temp       = [
            'UUIDList'  => [
                    [
                        'UUID'       => $routeUUID,
                        'WalletAddr' => $data['fd_walletAdr']
                    ],
                ],
            'EmailAddr' => $emailAddr,
            'Type'      => 0,
        ];
        //锁定
        $rst = Curl::post(self::$CashRedemption,json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);
        if( 2 != $rst['status']){
            return jorx(['code' => 400,'msg' => '网络拥堵,请五分钟后重试!']);
        }
        //提现
        $temp['Type'] = 1;
        $rst = Curl::post(self::$CashRedemption,json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);
        if( 2 != $rst['status']){
            return jorx(['code' => 400,'msg' => '提现不成功!']);
        }
        $value_total = $rst['ResultList'][0]['value_total'];
        $orderId     = $rst['OrderId'];
        Db::startTrans();
        try{
            //交易市场原状态
            $coinsbc = Db::table('wkj_user_coinsbc')->where('userid',$data['fd_walltId'])->find();
            if(!$coinsbc){
                $coinsbc['sbc'] = $coinsbc['sbcd'] = $coinsbc['sbc'] =  $coinsbc['sbcd'] = 0;
            }
            //更新交易市场
            if(Db::table('wkj_user_coinsbc')->where('userid',$data['fd_walltId'])->find()){

                Db::table('wkj_user_coinsbc')->where('userid',$data['fd_walltId'])->setInc('sbc',$value_total);
                $sbc = Db::name('wallet_user')->where('fd_id' , $data['fd_walletUserId'])->value('fd_sbcNums');
                $sbc = bcadd($sbc , $value_total , 10);
                Db::name('wallet_user')->where('fd_id' , $data['fd_walletUserId'])->setField('fd_sbcNums' , $sbc);

            }else{
                Db::table('wkj_user_coinsbc')->insert([
                    'userid' => $data['fd_walltId'],
                    'sbc'    => $value_total,
                ]);
            }
            //历史记录
            $history = [
                'userid'   => $data['fd_walltId'],
                'coinname' => 'sbc',
                'num_a'    => $coinsbc['sbc'],
                'num_b'    => $coinsbc['sbcd'],
                'num'      => $coinsbc['sbc'] + $coinsbc['sbcd'],
                'fee'      => $value_total,
                'type'     => 1,
                'name'     => 'mycz',
                'nameid'   => $data['fd_walltId'],
                'mum_a'    => $coinsbc['sbc'] + $value_total,
                'mum_b'    => $coinsbc['sbcd'],
                'mum'      => $coinsbc['sbc'] + $value_total + $coinsbc['sbcd'],
                'remark'   => '充值',
                'addtime'  => time(),
                'status'   => 1,
            ];
            Db::table('wkj_finance_sbc')->insert($history);
            //路由器提现记录
            Db::table('wallet_recharge')->insert([
                'fd_routerUUid' => $routeUUID,
                'fd_walletId'   => $data['fd_walltId'],
                'fd_walletAdr'  => $data['fd_walletAdr'],
                'fd_wtuserId'   => $data['fd_walletUserId'],
                'fd_sbcNums'    => $value_total,
                'fd_status'     => 0,
                'fd_createDate' => date('Y-m-d H:i:s')
            ]);
            // 提交事务
            Db::commit();
            return jorx(['code' => 200,'msg' => '提现成功!','num' => $value_total]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            //回滚
            $rst = Curl::post(self::$WithdrawalRollBack,json_encode(['OrderID' => $orderId]),['Content-Type: application/json']);
            //失败记录日志
            if($rst != 2){
                file_put_contents('./error.log',date('Y-m-d H:i:s') .'===>orderID:'. $orderId . "\r\n" ,FILE_APPEND);
            }
            return jorx(['code' => 400,'msg' => '提现失败，请稍后再试!']);
        }
    }

    /**
     * 钱包-路由器解绑
     * @param Request $request
     * @return \think\response\Json
     */
    public function untieWallet(Request $request)
    {
        $routerUUid = $request->param('routerUUid','');
        if(36 !== strlen($routerUUid)){
            return jorx(['code' => 400,'msg' => '路由器uuid不合法!']);
        }
        //是否已经绑定
        if(!Db::table('rt_wallet')->where('fd_routerUUid',$routerUUid)->where('fd_status',1)->find()){
            return jorx(['code' => 400,'msg' => '该路由器还未绑定钱包!']);
        }
        //解绑
        if(Db::table('rt_wallet')->where('fd_routerUUid',$routerUUid)->where('fd_status',1)->update(['fd_status' => 0])){
            return jorx(['code' => 200,'msg' => '解绑成功!']);
        }
        return jorx(['code' => 400,'msg' => '解绑失败，请稍后再试!']);
    }
}