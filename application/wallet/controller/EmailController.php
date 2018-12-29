<?php


namespace app\wallet\controller;


use app\common\lib\GeetestLib;
use app\wallet\model\Emailrecord;
use app\wallet\model\User;
use app\wallet\model\WalletSendEmailIPTimesModel;
use app\wallet\service\Email;
use redis\Redis;
use think\Db;
use think\Exception;
use think\helper\Str;
use think\Request;
use think\Config;
use think\Validate;

class EmailController extends BaseController
{

    /**
     * 注册用户时 邮箱验证
     * @param Request $request
     */
    public function check(Request $request)
    {
        if ($request->isPost()) {
            $email = $request->param('email', '');
            $code = $request->param('code', '');
            if ($code == null || $email == null) {
                return json(['code' => 402, 'msg' => '参数错误!']);
            } else {
                //验证邮箱
                $check_data = ['fd_userName' => $email];
                $rule = [
                    'fd_userName' => 'email|unique:wallet_user',
                ];
                $msg = [
                    'fd_userName.email' => '邮箱格式错误!',
                    'fd_userName.unique' => '该邮箱已被注册!',
                ];
                $validate = new Validate($rule, $msg);

                //获取check表中数据
                //$check = Db::table('wallet_email_check')
                //    ->where('email', '=', $email)
                //    ->find();

                //未注册邮箱可进行解密操作
                if ($validate->check($check_data)) {
                    //加密方式
                    $method = 'AES-128-CBC';
                    //第一次加密参数
                    $key1 = 'gehua20181108001';
                    $iv1 = '201809011300001x';
                    //第二次加密参数
                    $key2 = 'starbridgechain1';
                    $iv2 = '201808300630002x';
                    //第一次解码
                    try{
                        $first_de = openssl_decrypt($code, $method, $key2, 2, $iv2);
                    }catch (Exception $e){
                        return json(['code' => 404, 'msg' => '验证失败3!']);
                    }
                    //第一次解密失败
                    if ($first_de === false) {
                        return json(['code' => 404, 'msg' => '验证失败3!']);
                    }
                    //处理
                    $first_arr = explode('*', $first_de);
                    //$app_code = iconv('gbk','utf-8',$first_arr[0]);
                    $app_code = $first_arr[0];

                    //第二次解码
                    try{
                        $second_de = openssl_decrypt($app_code, $method, $key1, 2, $iv1);
                    }catch (Exception $e){
                        return json(['code' => 405, 'msg' => '验证失败2!']);
                    }
                    //第二次解密失败
                    if ($second_de === false) {
                        return json(['code' => 405, 'msg' => '验证失败2!']);
                    }
                    $second_de = iconv('gbk','utf-8',$second_de);

                    $first = substr(trim($second_de),0,35);
                    $second = substr(trim($first_arr[1]),0,35);

                    //两次解密成功
                    if ($first == $second) {
                        //获取配置参数
                        $um_count = Db::table('wallet_config')
                            ->where('fd_id', '=', 1)
                            ->value('fd_uuid_mac_count');
                        //mac地址出现次数
                        $mac_count = Db::table('wallet_email_check')
                            ->where('mac', '=', $first_arr[2])
                            ->count();
                        //uuid出现次数
                        $uuid_count = Db::table('wallet_email_check')
                            ->where('uuid', '=', $first_arr[1])
                            ->count();

                        //是否再配置之内
                        if ($mac_count < $um_count && $uuid_count < $um_count) {
                            $data = [
                                'email' => $email,
                                'mac' => $first_arr[2],
                                'uuid' => $first_arr[1],
                                'createDate' => time(),
                            ];

                            //获取check表中数据
                            $check = Db::table('wallet_email_check')
                                ->where('email', '=', $email)
                                ->find();

                            //新增 OR 修改  信息
                            if ($check){
                                $res = Db::table('wallet_email_check')
                                    ->where('id','=',$check['id'])
                                    ->update($data, true);
                            }else{
                                $res = Db::table('wallet_email_check')
                                    ->insert($data, true);
                            }
                            //
                            if ($res) {
                                return json(['code' => 200, 'msg' => '验证成功!']);
                            } else {
                                return json(['code' => 408, 'msg' => '验证失败1!']);
                            }
                        } else {
                            return json(['code' => 407, 'msg' => '验证失败!超过次数!']);
                        }

                    } else {
                        //uuid不一致
                        return json(['code' => 406, 'msg' => '验证失败!',$first,$second]);
                    }
                } else {
                    return json(['code' => 403, 'msg' => $validate->getError()]);
                }
            }
        } else {
            return json(['code' => 401, 'msg' => '请求方式错误!']);
        }
    }

    /**
     * 注册时发送邮件之前获取code
     * @param Request $request
     * @return \think\response\Json
     */
    public function sendRec(Request $request)
    {
        $email = $request->param('email', '');
        $ip = $request->ip();
        $key = $email . 'AND' . $ip;
        $value = spread_code(4);
        $redis = Redis::instance();
        $redis->setex($key, 90, $value);
        return json(['code' => 200, 'reg_code' => $value]);
    }

    /**
     * 第一次验证
     * @param Request $request
     */
    public function startCaptcha(Request $request)
    {
        $captcha_id = Config::get('Geetest.CAPTCHA_ID');
        $private_key = Config::get('Geetest.PRIVATE_KEY');
        $GtSdk = new GeetestLib($captcha_id, $private_key);
        $redis = Redis::instance();

        $data = array(
            "user_id" => "test", # 网站用户id
            "client_type" => "web", #web:电脑上的浏览器；h5:手机上的浏览器，包括移动应用内完全内置的web_view；native：通过原生SDK植入APP应用的方式
            "ip_address" => $request->ip() # 请在此处传输用户请求验证时所携带的IP
        );
        $status = $GtSdk->pre_process($data, 1);
        $redis->set('gtserver', $status);
        //$redis->set('user_id',$data['user_id']);
        //$_SESSION['gtserver'] = $status;
        //$_SESSION['user_id'] = $data['user_id'];
        echo $GtSdk->get_response_str();
    }

    /**
     * 注册时验证码发送
     * @param Request $request
     * @param User $user
     * @param Emailrecord $emailrecord
     * @return \think\response\Json
     */
    public function sendReg(Request $request, User $user, Emailrecord $emailrecord)
    {
        $email = $request->param('email', '');

        //验证邮箱是否为app验证过的邮箱
        $e_res = Db::table('wallet_email_check')
            ->where('email', '=', $email)
            ->count();
        if ($e_res != 1) {
            return jorx(['code' => 402, 'msg' => '邮箱未验证!!']);
        } else {

            //极验验证是否成功!
            //$result = $this->secondCaptcha($request);
            //if (!$result) {
                //return jorx(['code' => 400, 'msg' => '验证失败!!']);
            //} else {

                $rst = filter_var($email, FILTER_VALIDATE_EMAIL);
                if (!$rst) {
                    return jorx(['code' => 400, 'msg' => '邮箱格式错误!']);
                }

                //邮箱格式必须是指定的邮箱后缀
                $emailSuffix = Db::table('wallet_email_suffix')
                    ->column('suffix');
                $enable = strrchr($email, '@');
                if (!in_array($enable, $emailSuffix)) {
                    return jorx(['code' => 400, 'msg' => '发送验证码失败!请联系管理员!']);
                }

                //判断是否获取了reg_code
                $redis = Redis::instance();
                $email = $request->param('email', '');
                $ip = $request->ip();
                $key = $email . 'AND' . $ip;
                $reg_code = $request->param('reg_code', '');
                $code = $redis->get($key);

                if ($reg_code) {
                    if ($code) {
                        if ($reg_code != $code) {
                            return json(['code' => 400, 'msg' => '参数错误C1']);
                        }
                    } else {
                        return json(['code' => 400, 'msg' => '参数错误C2']);
                    }
                } else {
                    return json(['code' => 400, 'msg' => '参数错误C3']);
                }

                //限制IP发送次数!
                $IP_addr = $request->ip();
                $IP_data = WalletSendEmailIPTimesModel::where('IP_addr', $IP_addr)
                    ->find();
                $reg_config = Db::table('wallet_config')
                    ->field('fd_ipNum,fd_sendEmail_space')
                    ->where('fd_id', 1)
                    ->find();

                if ($IP_data) {
                    if (strtotime($IP_data['updateDate']) + ($reg_config['fd_sendEmail_space'] * 60) <= time()) {
                        $IP_data->where('IP_addr', $IP_addr)->update([
                            'sendEmailTimes' => 0,
                            'updateDate' => time(),
                        ]);
                        $IP_data['sendEmailTimes'] = 0;
                    }
                    if ($IP_data['sendEmailTimes'] >= $reg_config['fd_ipNum']) {
                        return jorx(['code' => 404, 'msg' => '发送验证码失败!请联系管理员!!!']);
                    }
                }

                //验证发送频率
                $redis = Redis::instance();
                $key = config('redis.prefix') . 'reg:' . $email;
                $data = json_decode($redis->get($key), true);
                if ($data && isset($data['last_time']) && time() - $data['last_time'] < 60) {
                    return jorx(['code' => 400, 'msg' => '60秒内只能发送一次!']);
                }
                $result = $user->where('fd_email', $email)->field('fd_id')->find();
                if ($result) {
                    return jorx(['code' => 400, 'msg' => '该邮箱已被注册!']);
                }
                //发送验证码
                $code = Str::random(6);
                $title = config('email.title');
                $msg = str_replace('{{code}}', $code, config('email.verification'));
                if (Email::sendEmail1($title, [$email], $msg)) {
                    //存入redis 有效期30分钟
                    $redis->setex($key, 1800, json_encode(['last_time' => time(), 'code' => $code]));
                    //写入邮件发送记录
                    $emailrecord->data(['fdt_msg' => $msg, 'fd_sendEmail' => config('email.username'), 'fd_receiveEmail' => $email])->save();

                    //写入发送邮件IP的次数
                    if ($IP_data) {
                        $IP_data->where('IP_addr', $IP_addr)->setInc('sendEmailTimes');
                    } else {
                        WalletSendEmailIPTimesModel::create([
                            'IP_addr' => $IP_addr,
                            'sendEmailTimes' => 1,
                        ]);
                    }
                    return jorx(['code' => 200, 'msg' => '发送成功!']);
                }
                return jorx(['code' => 400, 'msg' => '发送失败，请稍后再试!']);
            }
        //}
    }


    /**
     * 找回密码时发送验证码
     * @param Request $request
     * @param User $user
     * @param Emailrecord $emailrecord
     * @return \think\response\Json
     */
    public function sendRet(Request $request, User $user, Emailrecord $emailrecord)
    {
        $email = $request->param('email', '');
        $rst = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$rst) {
            return jorx(['code' => 400, 'msg' => '邮箱格式错误!']);
        }
        //验证发送频率
        $redis = Redis::instance();
        $key = config('redis.prefix') . 'retrieve:' . $email;
        $data = json_decode($redis->get($key), true);
        if ($data && isset($data['last_time']) && time() - $data['last_time'] < 60) {
            return jorx(['code' => 400, 'msg' => '60秒内只能发送一次!']);
        }
        $result = $user->where('fd_email', $email)->field('fd_id')->find();
        if (!$result) {
            return jorx(['code' => 400, 'msg' => '该用户不存在!']);
        }
        //发送验证码
        $code = Str::random(6);
        $title = config('email.title');
        $msg = str_replace('{{code}}', $code, config('email.verification'));
        if (Email::sendEmail2($title, [$email], $msg)) {
            //存入redis 有效期30分钟
            $redis->setex($key, 1800, json_encode(['last_time' => time(), 'code' => $code]));
            //写入邮件发送记录
            $emailrecord->data(['fdt_msg' => $msg, 'fd_sendEmail' => config('email.username'), 'fd_receiveEmail' => $email])->save();
            return jorx(['code' => 200, 'msg' => '发送成功!']);
        }
        return jorx(['code' => 400, 'msg' => '发送失败，请稍后再试!']);
    }

    /**
     * 人工注册 发送验证码 到玉庒邮箱!
     * @param $email
     * @param Emailrecord $emailrecord
     * @return \think\response\Json
     */
    public function sendReg_y($email, Emailrecord $emailrecord)
    {
//        $email = $request->param('email','');
        $rst = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$rst) {
            return jorx(['code' => 400, 'msg' => '邮箱格式错误!']);
        }

        //验证发送频率
        $redis = Redis::instance();
        $key = config('redis.prefix') . 'reg:' . $email;
        $data = json_decode($redis->get($key), true);
        if ($data && isset($data['last_time']) && time() - $data['last_time'] < 20) {
            return jorx(['code' => 400, 'msg' => '20秒内只能发送一次!']);
        }
        $result = Db::table('wallet_user')
            ->where('fd_email', $email)
            ->field('fd_id')
            ->find();
        if ($result) {
            return jorx(['code' => 400, 'msg' => '该邮箱已被注册!']);
        }
        //发送验证码
        $code = Str::random(6);
        $title = config('email.title');
        $msg = str_replace('{{code}}', $code, config('email.verification'));
        if (Email::sendEmail3($title, ['starbridgechain@outlook.com'], $msg)) {
            //存入redis 有效期5分钟
            $redis->setex($key, 300, json_encode(['last_time' => time(), 'code' => $code]));
            //写入邮件发送记录
            $emailrecord->data(['fdt_msg' => $msg, 'fd_sendEmail' => config('email.username'), 'fd_receiveEmail' => 'starbridgechain@outlook.com'])->save();
            return jorx(['code' => 200, 'msg' => '发送成功!']);
        }
        return jorx(['code' => 400, 'msg' => '发送失败，请稍后再试!']);
    }


    /**
     * 第二次验证
     * @param Request $request
     */
    private function secondCaptcha($request)
    {
        $captcha_id = Config::get('Geetest.CAPTCHA_ID');
        $private_key = Config::get('Geetest.PRIVATE_KEY');
        $redis = Redis::instance();
        $gtserver = $redis->get('gtserver');
        $GtSdk = new GeetestLib($captcha_id, $private_key);

        if (!isset($_POST['geetest_challenge']) || !isset($_POST['geetest_validate']) || !isset($_POST['geetest_seccode'])) {
            return false;
        }

        $data = array(
            "user_id" => "test",
            "client_type" => "web", #web:电脑上的浏览器；h5:手机上的浏览器，包括移动应用内完全内置的web_view；native：通过原生SDK植入APP应用的方式
            "ip_address" => $request->ip() # 请在此处传输用户请求验证时所携带的IP
        );

        if ($gtserver == 1) {   //服务器正常
            $result = $GtSdk->success_validate($_POST['geetest_challenge'], $_POST['geetest_validate'], $_POST['geetest_seccode'], $data);
            if ($result) {
                return true;
            } else {
                return false;
            }
        } else {  //服务器宕机,走failback模式
            if ($GtSdk->fail_validate($_POST['geetest_challenge'], $_POST['geetest_validate'], $_POST['geetest_seccode'])) {
                return true;
            } else {
                return false;
            }
        }
    }
}