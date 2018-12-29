<?php


namespace app\wallet\controller;


use app\common\lib\GeetestLib;
use app\wallet\model\Logrecord;
use app\wallet\model\Purse;
use app\wallet\model\User;
use app\wallet\service\Email;
use redis\Redis;
use think\Config;
use think\Db;
use think\Exception;
use think\helper\Hash;
use think\helper\Str;
use think\Request;

use app\wallet\controller\PurseController;

class UserController extends BaseController
{

    /**
     * 用户注册
     * @param Request $request
     * @param User $user
     * @return \think\response\Json
     */
    public function reg(Request $request,User $user,Purse $purse)
    {
        $param = $request->param();
        $result = $this->validate($param,'Reg');
        $type = $request->param('type');
        if(true !== $result){
            return jorx(['code' => 400,'msg' => '参数错误:'.$result]);
        }

        //验证邮箱是否为app验证过的邮箱
        $e_res = Db::table('wallet_email_check')
            ->where('email', '=', $request->param('email'))
            ->count();
        if ($e_res != 1) {
            return jorx(['code' => 402, 'msg' => '邮箱未验证!!']);
        }else{
            //验证码
            $regCode  = strtolower(isset($param['code']) ? $param['code'] : '');
            $redis    = Redis::instance();
            $key      = config('redis.prefix') . 'reg:' . $param['email'];
            $data     = json_decode($redis->get($key),true);
            $code     = strtolower(isset($data['code']) ? $data['code'] : '');
            if('' == $code || $code != $regCode){
                return jorx(['code' => 400,'msg' => '验证码错误!']);
            }
            //删除验证码
            $redis->del($key);

            //邮箱格式必须是指定的邮箱后缀
            $emailSuffix = Db::table('wallet_email_suffix')
                ->column('suffix');
            $enable = strrchr($param['email'],'@');
            if (!in_array($enable,$emailSuffix)){
                return jorx(['code' => 400,'msg' => '注册失败!请联系管理员!']);
            }

            //注册
            $rst  = $user->where('fd_email',$param['email'])->field('fd_id')->find();
            if($rst){
                return jorx(['code' => 400,'msg' => '该邮箱已被注册!']);
            }

            //获取配置信息
            $reg_config = Db::table('wallet_config')
                ->field('fd_ipNum')
                ->where('fd_id',1)
                ->find();

            //判断该ip已经注册的次数
            $ip_count = $user->where('fd_regIP',$request->ip())->count();
            if ($ip_count > $reg_config['fd_ipNum']){
                return jorx(['code' => 400,'msg' => '注册失败!请联系管理员!!!']);
            }
            $save_data = [];
            //判断注册用户是否填写了邀请码
            if (!empty($param['spreadCode'])){      //填写了推广码
                //推广码的合法性(推广人的id)
                $first = $user->where('fd_spreadCode',$param['spreadCode'])
                    ->field('fd_id,fd_invitationCode')->find();
                if ($first == null){
                    return jorx(['code' => 400,'msg' => '该推广码不存在!']);
                }

                //一级推广码
                $save_data['fd_invitationCode'] = $param['spreadCode'];
                //二级推广码
                $save_data['fd_higherInvitationCode'] = $first['fd_invitationCode'];
            }

            //生成个人唯一推广码
            $spread_code = spread_code(8);
            $is_unique = $user->where('fd_spreadCode',$spread_code)
                ->field('fd_id')
                ->find();
            if ($is_unique){
                $spread_code = spread_code(8);
            }

            $salt = Str::random(6);

            //创建新用户时需要的  基本数据
            $save_data['fd_email']  = $param['email'];
            $save_data['fd_userName']  = $param['email'];
            $save_data['fd_salt']  = $salt;
            $save_data['fd_psd']  = Hash::make($param['password'],'md5',['salt' => $salt]);
            $save_data['fd_lastActivity']  = date('Y-m-d H:i:s');
            $save_data['fd_userCode']  = accessToken();
            $save_data['fd_spreadCode']  = $spread_code;
            $save_data['fd_regIP']  = $request->ip();
            //开启事务  新用户注册
            Db::startTrans();
            try{
                //创建用户
//            if($type==1){   //判断是否是特殊账户
//                $user->data([
//                    'fd_email'          => $param['email'],
//                    'fd_userName'       => $param['email'],
//                    'fd_salt'           => $salt,
//                    'fd_psd'            => Hash::make($param['password'],'md5',['salt' => $salt]),
//                    'fd_lastActivity'   => date('Y-m-d H:i:s'),
//                    'fd_userCode'       => accessToken(),
//                    'fd_type'           => 1,
//                ])->save();
//            }else{
//                $user->data([
//                    'fd_email'          => $param['email'],
//                    'fd_userName'       => $param['email'],
//                    'fd_salt'           => $salt,
//                    'fd_psd'            => Hash::make($param['password'],'md5',['salt' => $salt]),
//                    'fd_lastActivity'   => date('Y-m-d H:i:s'),
//                    'fd_userCode'       => accessToken(),
//                ])->save();
//            }

                //判断是否是特殊账户
                if ($type==1){
                    $save_data['fd_type'] = 1;
                }

                //注册新用户
                $user->data($save_data)->save();

                $userID = $user->getLastInsID();
                //交易市场数据同步
                Db::table('wkj_user')->insert([
                    'id'          => 'cny' . $userID,
                    'username'    => $user['fd_email'],
                    'payencrypt'  => $user['fd_salt'],
                    'paypassword' => $user['fd_psd'],
                    'status'      => 1,
                ]);

                //创建btc账户
                Db::table('wkj_user_coinbtc')->insert([
                    'userid' => $userID,
                ]);

                //fbtc账户
                Db::table('wkj_user_coinfbtc')->insert([
                    'userid' => 'cny' . $userID,
                ]);

                //cny账户
                Db::table('wkj_user_coincny')->insert([
                    'userid' => 'cny' . $userID,
                ]);
                //-------------------------------------------------------------
                $purseAddress = createPurse();
                //创建钱包
                $purse->data([
                    'fd_userId'         => $userID,
                    'fd_walletAdr'      => $purseAddress,
                    'fd_psd'            => $user['fd_psd'],
                    'fd_salt'           => $user['fd_salt'],
                    'fd_status'         => 1,
                    'fd_isRelieve'      => 1,
                ])->save();
                $purseId = $purse->getLastInsID();
                //用户分组
                Db::table('wallet_group_purse')->insert([
                    'user_id'=>$userID,
                    'purse_id'=>$purseId,
                    'group_id'=>2,
                ]);

                //btc账户和钱包
                Db::table('wallet_purse_BBTC')->insert([
                    'fd_walletId'   => $purseId,
                    'fd_wtuserId'   => $userID,
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
                Db::commit();
                return jorx(['code' => 200,'msg' => '注册成功!']);
            }catch (Exception $exception){
                Db::rollback();
                return jorx(['code' => 400,'msg' => $exception->getMessage()]);
                return jorx(['code' => 400,'msg' => '注册失败，请稍后再试!','error' => $exception->getMessage()]);
            }
        }

    }

    /**
     * 找回密码
     * @param Request $request
     * @param User $user
     * @param Purse $purse
     * @return \think\response\Json
     */
    public function retrievePsd(Request $request,User $user,Purse $purse)
    {
        $param = $request->param();
        $result = $this->validate($param,'Reg');
        if(true !== $result){
            return jorx(['code' => 400,'msg' => '参数错误:'.$result]);
        }
        //验证码
        $regCode  = strtolower(isset($param['code']) ? $param['code'] : '');
        $redis    = Redis::instance();
        $key      = config('redis.prefix') . 'retrieve:' . $param['email'];
        $data     = json_decode($redis->get($key),true);
        $code     = strtolower(isset($data['code']) ? $data['code'] : '');
        if('' == $code || $code != $regCode){
            return jorx(['code' => 400,'msg' => '验证码错误!']);
        }
        //删除验证码
        $redis->del($key);
        //重置密码
        $rst  = $user->where('fd_email',$param['email'])->field('fd_id')->find();
        if(!$rst){
            return jorx(['code' => 400,'msg' => '用户不存在!']);
        }
        $salt = Str::random(6);
        $rst['fd_psd']  = Hash::make($param['password'],'md5',['salt' => $salt]);
        $rst['fd_salt'] = $salt;
        Db::startTrans();
        try{
            //重置用户密码
            $rst->save();
            //钱包id
            $wallet_ids = $purse->where('fd_userId',$rst['fd_id'])->column('fd_id');

            //更新钱包密码
            Db::table('wallet_purse')->where('fd_id','in',$wallet_ids)->update([
                'fd_psd'  => $rst['fd_psd'],
                'fd_salt' => $rst['fd_salt']
            ]);

            //更新交易市场密码
            Db::table('wkj_user')->where('id','in',$wallet_ids)->update([
                'paypassword' => $rst['fd_psd'],
                'payencrypt'  => $rst['fd_salt']
            ]);
            //更新交易市场cny密码
            Db::table('wkj_user')->where('id','cny' . $rst['fd_id'])->update([
                'paypassword' => $rst['fd_psd'],
                'payencrypt'  => $rst['fd_salt']
            ]);
            // 提交事务
            Db::commit();
            return jorx(['code' => 200,'msg' => '重置密码成功!']);
        }catch (\Exception $e){
            Db::rollback();
            return jorx(['code' => 400,'msg' => '重置密码失败，请稍后再试!']);
        }
    }

    /**
     * 登录
     * @param Request $request
     * @param User $user
     * @param Logrecord $logrecord
     * @return \think\response\Json
     */
    public function login(Request $request, User $user,Logrecord $logrecord)
    {
        $param = $request->param();
        $rst   = $this->validate($param,'Login');
        if(true !== $rst){
            return jorx(['code' => 400,'msg' => '参数错误:' . $rst]);
        }
        $data  = $user->where('fd_email',$param['username'])->find();
        if(!$data){
            return jorx(['code' => 400,'msg' => '用户名或密码错误!']);
        }
        if(!Hash::check($param['password'],$data['fd_psd'],'md5',['salt' => $data['fd_salt']])){
            return jorx(['code' => 400,'msg' => '用户名或密码错误!']);
        }
        //登录日志
        $logrecord->data([
            'fd_userId' => $data['fd_id'],
            'fd_log'    => '登录',
            'fd_ip'     => $request->ip()
        ])->save();


        //推广获利的配置及开关
        $reg_config = Db::table('wallet_config')
            ->field('fd_firSpread,fd_secSpread,fd_regNum,fd_walletAdr,fd_spread_on_off,fd_login_min_space,fd_uuid_mac_count')
            ->where('fd_id',1)->find();

        //最近活动时间和登录次数
        $data['fd_lastActivity'] = date('Y-m-d H:i:s');


        $spaceTime = date('Y-m-d H:i:s',time()-$reg_config['fd_login_min_space']*60);

        //如果最后活动时间
        if ($data['fd_gt_24hour_lastLoginDate'] == null && $data['fd_gt_24hour_loginTimes'] == 0){
            $data['fd_gt_24hour_lastLoginDate'] = date('Y-m-d H:i:s');
            $data['fd_gt_24hour_loginTimes'] = 1;

        }elseif ($data['fd_gt_24hour_lastLoginDate'] < $spaceTime){
            $data['fd_gt_24hour_lastLoginDate'] = date('Y-m-d H:i:s');
            $data['fd_gt_24hour_loginTimes'] += 1;

        }

        $data['fd_logTimes']     = $data['fd_logTimes'] + 1;
        $data->save();

        //获取黑名单!
        $blackList = Db::table('wallet_black_list')
            ->column('email');

        if ($reg_config['fd_spread_on_off'] == 1) { //开关分支
            if (!in_array($data['fd_email'],$blackList)) { //不在黑名单分支
                //判断 2018-08-28 14:00:00 后注册的 间隔大于24小时的登录次数 达到3次就发放奖励
                if ($data['fd_createDate'] >= '2018-08-29 16:00:00' && $data['fd_createDate'] <= '2018-09-13 00:00:00' && $data['fd_gt_24hour_loginTimes'] >= 3) {
                    $adr = [];
                    //登录成功 判断是否是未发放奖励
                    if ($data['fd_giftNums'] == null) {
                        if ($data['fd_invitationCode'] != null) {    //有一级推广人
                            //一级推广人的钱包信息
                            $first_data = Db::table('wallet_user')->alias('wu')
                                ->distinct(true)
                                ->field('wp.fd_walletAdr as adr,wu.fd_invitationCode as secCode,wu.fd_id as id')
                                ->join('wallet_purse wp', 'wp.fd_userId = wu.fd_id')
                                ->where('wu.fd_spreadCode', $data['fd_invitationCode'])
                                ->find();
                            $adr['first'] = [
                                'id' => $first_data['id'],
                                'adr' => $first_data['adr'],
                            ];
                            if ($first_data['secCode'] != null) {
                                //二级推广人的钱包信息
                                $second_data = Db::table('wallet_user')->alias('wu')
                                    ->distinct(true)
                                    ->field('wp.fd_walletAdr as adr,wu.fd_id as id')
                                    ->join('wallet_purse wp', 'wp.fd_userId = wu.fd_id')
                                    ->where('wu.fd_spreadCode', $first_data['secCode'])
                                    ->find();
                                $adr['second'] = [
                                    'id' => $second_data['id'],
                                    'adr' => $second_data['adr'],
                                ];
                            }
                        }  //判断推广关系 结束

                        //开启事务
                        Db::startTrans();
                        try {
                            //新注册人 添加sdt
                            $end_walletAdr = Db::table('wallet_purse')
                                ->where('fd_userId', $data['fd_id'])
                                ->value('fd_walletAdr');

                            Db::table('wallet_user')
                                ->where('fd_id', $data['fd_id'])
                                ->update(['fd_giftNums' => $reg_config['fd_regNum']]);
                            $this->walletTransfer($data['fd_id'], $reg_config['fd_walletAdr'], $end_walletAdr, $reg_config['fd_regNum']);

                            //有推广人
                            if (count($adr) == 1) {
                                //推广人获得的sdt总和
                                $reward = $reg_config['fd_firSpread'] + $reg_config['fd_secSpread'];
                                //推广人划入sdt
                                $this->walletTransfer($data['fd_id'], $reg_config['fd_walletAdr'], $adr['first']['adr'], $reward);
                                //写入记录
                                Db::table('wallet_reward_hand_out_log')->insert(
                                    [
                                        'user_id' => $adr['first']['id'],
                                        'reward' => $reward,
                                        'createDate' => time(),
                                    ]
                                );
                            } elseif (count($adr) == 2) {
                                //一级推广人划入sdt
                                $this->walletTransfer($data['fd_id'], $reg_config['fd_walletAdr'], $adr['first']['adr'], $reg_config['fd_firSpread']);
                                Db::table('wallet_reward_hand_out_log')->insert(
                                    [
                                        'user_id' => $adr['first']['id'],
                                        'reward' => $reg_config['fd_firSpread'],
                                        'createDate' => time(),
                                    ]
                                );

                                //二级推广人划入sdt
                                $this->walletTransfer($data['fd_id'], $reg_config['fd_walletAdr'], $adr['second']['adr'], $reg_config['fd_secSpread']);
                                Db::table('wallet_reward_hand_out_log')->insert(
                                    [
                                        'user_id' => $adr['second']['id'],
                                        'reward' => $reg_config['fd_secSpread'],
                                        'createDate' => time(),
                                    ]
                                );
                            }
                            Db::commit();
                        } catch (Exception $exception) {
                            Db::rollback();
                            return jorx(['code' => 400, 'msg' => '登录失败，请稍后再试!', 'error' => $exception->getMessage()]);
                        }

                    }  //判断发放奖励为空 结束
                }  //第三次登录时间与上一次间隔24小时 结束

            }  //黑名单结束

        }  //开关结束

        //保存用户信息
        $redis  = Redis::instance();
        $token  = accessToken();
        $prefix = config('redis.prefix');
        //登录hash表
        $list_token = $redis->hGet($prefix . 'loginHash',$data['fd_id']);
        if($list_token && 36 == strlen($list_token)){
            $redis->del($prefix . 'token:' . $list_token);
        }
        //登录hash表
        $redis->hSet($prefix . 'loginHash',$data['fd_id'],$token);
        //token键值
        $key   = $prefix . 'token:' . $token;

        //token有效期1个月
        $redis->setex($key ,2592000,json_encode($data));

        //登录成功发送邮件
//        $title = '登录成功';                //主题
//        $to = $param['username'];           //收件人
//        $body = $to.'在'.date('Y-m-d H:i:s').'登录，若非本人操作，请及时修改密码!
// '.$to.'（E-m（E-mail Address） is logged in at '.date('H:i:s').' on '.date('Y-m-d').'. Please change your password in time if you are not operating by yourself.';
//        Email::sendEmail2($title,[$to],$body);
        return json(['code' => 200,'msg' => '登录成功!','token' => $token,'userId' => $data['fd_id']]);
    }

    /**
     * 登录成功发送邮件
     * @param Request $request 传入登录的用户名(即邮箱)
     */
    private function loginSendEmail(Request $request)
    {
        //登录成功发送邮件
        //发送邮件
        $title = '登录成功';                //主题
        $to = $request->param('username');           //收件人
        $body = '<p>'.$to.'在'.date('Y-m-d H:i:s').'登录，若非本人操作，请及时修改密码!</p>'.
            $to.'（E-m（E-mail Address） is logged in at '.date('H:i:s').' on '.date('Y-m-d').
            '. Please change your password in time if you are not operating by yourself.';

        //验证发送频率
        $redis = Redis::instance();
        $emailKey   = 'login:' . $to;
        $emailLogin  = $redis->get($emailKey);

        if(!$emailLogin){ //没有数据说明可以发送

            if (Email::sendEmail($title,[$to],$body)){ //发送成功
                $redis->set($emailKey,time(),60);
                return json_encode(config('code.success'));
            }

        }
        //60秒内多次发送 或者 发送失败!
        $data = config('code.error');
        $data['massage'] = '60秒内多次发送 或者 发送失败!';
        return json_encode($data);
    }

    /**
     * 修改密码
     * @param Request $request
     * @param User $userModel
     * @param Purse $purse
     * @return \think\response\Json
     */
    public function changePsd(Request $request,User $userModel,Purse $purse)
    {
        $param  = $request->param('');
        $rst    = $this->validate($param,'ChangePsd');
        if(true !== $rst){
            return jorx(['code' => 400,'msg' => $rst]);
        }
        //用户信息
        $user   = $request->user;
        if(!Hash::check($param['prePassword'],$user['fd_psd'],'md5',['salt' => $user['fd_salt']])){
            return jorx(['code' => 400,'msg' => '原密码错误!']);
        }
        //修改新密码
        $user['fd_psd'] = Hash::make($param['newPassword'],'md5',['salt' => $user['fd_salt']]);

        Db::startTrans();
        try{
            //修改用户密码
            $userModel->data($user)->allowField(true)->isUpdate(true)->save();
            //钱包id
            $wallet_ids = $purse->where('fd_userId',$user['fd_id'])->column('fd_id');
            //更新钱包密码
            Db::table('wallet_purse')->where('fd_id','in',$wallet_ids)->update([
                'fd_psd'  => $user['fd_psd'],
                'fd_salt' => $user['fd_salt']
            ]);
            //更新交易市场密码
            Db::table('wkj_user')->where('id','in',$wallet_ids)->update([
                'paypassword' => $user['fd_psd'],
                'payencrypt'  => $user['fd_salt']
            ]);
            //更新交易市场cny密码
            Db::table('wkj_user')->where('id','cny' . $user['fd_id'])->update([
                'paypassword' => $user['fd_psd'],
                'payencrypt'  => $user['fd_salt']
            ]);
            //修改redis状态
            $redis         =  Redis::instance();
            $token_key     = config('redis.prefix') . 'token:' . $request->accessToken;
            $redis->setex($token_key,2592000,json_encode($user));
            // 提交事务
            Db::commit();
            return jorx(['code' => 200,'msg' => '修改成功!']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return jorx(['code' => 400,'msg' => '修改失败,请稍后再试!']);
        }
    }

    /**
     * 退出
     * @param Request $request
     * @param Logrecord $logrecord
     * @return \think\response\Json
     */
    public function logout(Request $request,Logrecord $logrecord)
    {
        $token      = $request->accessToken;
        $redis      = Redis::instance();
        $prefix     = config('redis.prefix');
        $token_key  = $prefix . 'token:' . $token;
        $user       = $request->user;
        $ip         = $request->ip();
        $data       = [
            'fd_userId' => $user['fd_id'],
            'fd_log'    => '退出',
            'fd_ip'     => $ip
        ];
        //日志记录
        if($logrecord->data($data)->save()){
            $redis->del($token_key);
            return jorx(['code' => 200,'msg' => '退出成功!']);
        }
        return jorx(['code' => 400,'msg' => '退出异常，请稍后再试!']);
    }

    /**
     * 获取账户cny或btc余额
     * @param Request $request
     * @return \think\response\Json
     */
    public function getBalance(Request $request)
    {
        $coin_type = ['cny','fbtc','btc'];
        $type       = $request->param('type','');
        if(!in_array($type,$coin_type)){
            return jorx(['code' => 400,'msg' => '币种错误，只能是cny或btc或fbtc']);
        }
        $user  = $request->user;
        if('btc' == $type){
            $balance = Db::table('wkj_user_coin' . $type)->where('userid', $user['fd_id'])->value($type);
        }else{
            $balance = Db::table('wkj_user_coin' . $type)->where('userid','cny' . $user['fd_id'])->value($type);
        }
        !$balance && $balance = 0;
        return jorx(['code' => 200,'msg' => '获取成功!','balance' => number_format($balance,10,'.','')]);
    }

    /**
     * 获取全部币种的余额
     * @param Request $request
     * Purse $purse
     * @return \think\response\Json
     */
    public function getAllBalance(Request $request,Purse $purse)
    {
        $user   = $request->user;
        $btc    = number_format(Db::table('wkj_user_coinbtc')->where('userid', $user['fd_id'])->value('btc'),10,'.','');
        $fbtc   = number_format(Db::table('wkj_user_coinfbtc')->where('userid', 'cny' . $user['fd_id'])->value('fbtc'),10,'.','');
        $cny    = number_format(Db::table('wkj_user_coincny')->where('userid', 'cny' . $user['fd_id'])->value('cny'),10,'.','');
        $purse_ids = $purse->where('fd_userId',$user['fd_id'])->column('fd_id');
        $sbc    = number_format(Db::table('wkj_user_coinsbc')->where('userid','in',$purse_ids)->sum('sbc'),10,'.','');
        return jorx(['code' => 200,'msg' => '获取成功','data' => [
            'btc'   => n_f($btc),
            'fbtc'  => n_f($fbtc),
            'cny'   => n_f($cny),
            'sbc'   => n_f($sbc)
        ]]);
    }



    /**
     *
     * @param Request $request
     * @param Purse $purse
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getMyBalance(Request $request , Purse $purse)
    {
        $user   = $request->user;
        $btc_info = Db::table('wkj_user_coinbtc')->field('btc,btcd')->where('userid', $user['fd_id'])->find();
        $lock_sdtNums = Db::table('wallet_user')->where('fd_id', $user['fd_id'])->value('lock_sbcNums');

        $purse_ids = $purse->where('fd_userId',$user['fd_id'])->column('fd_id');
        $sdt = Db::table('wkj_user_coinsbc')->where('userid','in',$purse_ids)->sum('sbc');
        $sdt_d = Db::table('wkj_user_coinsbc')->where('userid','in',$purse_ids)->sum('sbcd');
        $btc_info['total_btc'] = bcadd($btc_info['btc'] , $btc_info['btcd'] , 15);

        $btc_info['info']=[$btc_info['btc'],$btc_info['btcd'],0];
        $sdt_info = [
            'sbc' => floatval($sdt),
            'sbcd' => floatval($sdt_d),
            'total_sbc' => floatval(bcadd($lock_sdtNums,bcadd($sdt , $sdt_d,15),15)),
            'lock_sdtNums'=>floatval($lock_sdtNums),
            'info'=>[
                floatval($sdt),floatval($sdt_d),floatval($lock_sdtNums)
            ]

        ];
        $result = [
            'msg' => '操作成功',
            'code' => 200,
        ];
        $result['btc_info'] = $btc_info;
        $result['sdt_info'] = $sdt_info;
        return json($result);
    }


    /**
     * 创建钱包
     * @param Request $request
     * @param Purse $purse
     * @return \think\response\Json
     */
    public function createWallet(Request $request,Purse $purse)
    {
        $user = $request->user;
        // 启动事务
        Db::startTrans();
        try{
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

            //用户分组
            Db::table('wallet_group_purse')->insert([
                'user_id'=>$user['fd_id'],
                'purse_id'=>$purseId,
                'group_id'=>2,
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
            // 提交事务
            Db::commit();
            return jorx(['code' => 200,'msg' => '创建成功!']);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return jorx(['code' => 400,'msg' => '创建失败!']);
        }
    }

    /**
     * 钱包转账
     * @param Request $request
     * @return \think\response\Json
     */
    private function walletTransfer($user_id,$begin_walletAdr,$end_walletAdr,$num)
    {
        //判断转账功能接口,是否可以使用,1是可以使用,0是不可以使用
        $file=file_exists("switch.json");
        if ($file){
            $json_string = file_get_contents("switch.json");
            $data = json_decode($json_string,true);

            if($data['switch'][0]==2){
                return jorx(['code' => 400,'msg' => '转账功能暂时关闭!']);
            }
        }else{

            $fp=fopen("switch.json","w+");
            fclose($fp);
            $data['switch'][0]=1;         //修改文件值
            $json_strings = json_encode($data);
            file_put_contents("switch.json",$json_strings);

        }

//        $begin_walletAdr = $request->param('begin_walletAdr');
//        $end_walletAdr = $request->param('end_walletAdr');
//        $num = $request->param('num');

        /*
        * 判断是否是 特殊账户提取 btc
        * */
        $user_info = Db::name('wallet_user')->where(['fd_id'=>$user_id])->find();

        if($user_info['fd_type']==1) {      //如果是特殊用用户,查看是否到btc可提取时间和可提取交易额
            $th_months_later=date('Y-m-d H:i:s',strtotime("+3 months",strtotime($user_info['fd_createDate'])));
            if(date("Y-m-d H:i:s")<$th_months_later) {       //三个月以后,可以提取
                return jorx(['code' => 400,'msg' => '3个月内不可转账!']);
            }
        }

        if(empty($begin_walletAdr))
        {
            return jorx(['code' => 400,'msg' => '转账钱包不存在!']);
        }
        else
        {
            $begin = Db::name('wallet_purse')->where('fd_walletAdr' , $begin_walletAdr)->find();
            if(empty($begin))
            {
                return jorx(['code' => 400,'msg' => '转账钱包不存在!']);
            }
        }
        if(empty($end_walletAdr))
        {
            return jorx(['code' => 400,'msg' => '收账钱包不存在!']);
        }
        else
        {
            $end = Db::name('wallet_purse')->where('fd_walletAdr' , $end_walletAdr)->find();
            if(empty($end))
            {
                return jorx(['code' => 400,'msg' => '收账钱包不存在!']);
            }
        }
        if($num <= 0)
        {
            return jorx(['code' => 400,'msg' => '请确认转账金额!']);
        }
        $sbc = Db::name('wkj_user_coinsbc')->where('userid' , $begin['fd_id'])->value('sbc');
        if($num > $sbc)
        {
            return jorx(['code' => 400,'msg' => '余额不足!']);
        }
        Db::startTrans();
        try
        {
            $fee = Db::table('wallet_fee')->where('fd_type' ,'sdt-out')->value('fd_fee');

            //转账用户sdt余额减去,转账的sdt数量
            $aa=bcsub($sbc,$num,10);
            $begin_re=Db::name('wkj_user_coinsbc')->where('userid' , $begin['fd_id'])->setField('sbc',$aa);
            //更新转账用户的sdt总余额，转账用户减去转账的sdt数量
            $begin_user = Db::name('wallet_user')->where('fd_id' , $begin['fd_userId'])->find();
            $sbcs = bcsub($begin_user['fd_sbcNums'],$num,10);
            Db::name('wallet_user')->where('fd_id' , $begin['fd_userId'])->setField('fd_sbcNums',$sbcs);

            if($begin_re)       //转账账户余额减少成功执行
            {
                //收账账户增加sdt数量
                $end_re=Db::name('wkj_user_coinsbc')->where('userid' , $end['fd_id'])->value('sbc');
                $bb=bcadd($end_re,$num,10);
                $en_re=Db::name('wkj_user_coinsbc')->where('userid' , $end['fd_id'])->setField('sbc',$bb);
                //更新转账用户的sdt总余额，原数量加上转账sdt数量
                $end_user = Db::name('wallet_user')->where('fd_id' , $end['fd_userId'])->find();
                $sbcs1 = bcadd($end_user['fd_sbcNums'],$num,10);
                Db::name('wallet_user')->where('fd_id' , $end['fd_userId'])->setField('fd_sbcNums',$sbcs1);

                if($en_re)  //接受成功
                {
                    $order_no = createOrderNo();
                    $after_sbc = Db::name('wkj_user_coinsbc')->where('userid' , $user_id)->value('sbc');
                    $ins_data['order_no'] = $order_no;
                    $ins_data['fd_begin_walletAdr'] = $begin_walletAdr;
                    $ins_data['fd_end_walletAdr'] = $end_walletAdr;
                    $ins_data['fd_transfer_sbc'] = $num;
                    $ins_data['fd_user_id'] = $user_id;
                    $ins_data['fd_col_user_id'] = $end['fd_userId'];
                    $ins_data['fd_before_sbc'] = $sbc;
                    $ins_data['fd_after_sbc'] = $after_sbc;
                    $ins_data['fd_fee'] = $fee;
                    $ins_data['created_time'] = date('Y-m-d H:i:s');
                    Db::name('wallet_transfer_log')->insert($ins_data);
                    Db::commit();
                    return jorx(['code' => 200,'msg' => '转账成功!']);
                }
                else
                {
                    Db::rollback();
                    return jorx(['code' => 400,'msg' => '转账失败，请稍后再试!']);
                }
            }
            else
            {
                Db::rollback();
                return jorx(['code' => 400,'msg' => '转账失败，请稍后再试!']);
            }
        }
        catch (\Exception $e)
        {
            Db::rollback();
            return jorx(['code' => 400,'msg' => '转账失败，请稍后再试!']);
        }
    }

    /**
     * 第一次验证
     * @param Request $request
     */
    public function startCaptcha(Request $request)
    {
        $captcha_id = Config::get('Geetest.CAPTCHA_ID');
        $private_key = Config::get('Geetest.PRIVATE_KEY');
        $GtSdk =  new GeetestLib($captcha_id, $private_key);
        @session_start();

        $data = array(
            "user_id" => "test", # 网站用户id
            "client_type" => "web", #web:电脑上的浏览器；h5:手机上的浏览器，包括移动应用内完全内置的web_view；native：通过原生SDK植入APP应用的方式
            "ip_address" => $request->ip() # 请在此处传输用户请求验证时所携带的IP
        );

        $status = $GtSdk->pre_process($data, 1);
        $_SESSION['gtserver'] = $status;
        $_SESSION['user_id'] = $data['user_id'];
        echo $GtSdk->get_response_str();
    }

    /**
     * 第二次验证
     * @param Request $request
     */
    private function secondCaptcha(Request $request)
    {
        $captcha_id = Config::get('Geetest.CAPTCHA_ID');
        $private_key = Config::get('Geetest.PRIVATE_KEY');
        @session_start();
        var_dump($captcha_id,$private_key);
        $GtSdk =  new GeetestLib($captcha_id, $private_key);

        $data = array(
            "user_id" => "test",
            "client_type" => "web", #web:电脑上的浏览器；h5:手机上的浏览器，包括移动应用内完全内置的web_view；native：通过原生SDK植入APP应用的方式
            "ip_address" => $request->ip() # 请在此处传输用户请求验证时所携带的IP
        );

        if ($_SESSION['gtserver'] == 1) {   //服务器正常
            $result = $GtSdk->success_validate($_POST['geetest_challenge'], $_POST['geetest_validate'], $_POST['geetest_seccode'], $data);
            if ($result) {
                echo '{"status":"success1"}';
            } else{
                echo '{"status":"fail1"}';
            }
        }else{  //服务器宕机,走failback模式
            if ($GtSdk->fail_validate($_POST['geetest_challenge'],$_POST['geetest_validate'],$_POST['geetest_seccode'])) {
                echo '{"status":"success2"}';
            }else{
                echo '{"status":"fail2"}';
            }
        }

    }
}