<?php


namespace app\wallet\controller;



use app\wallet\service\Email;
use curl\Curl;

use think\Db;
use think\Request;
use think\helper\Str;
use think\helper\Hash;
use app\wallet\model\User;

use think\Exception;

/*一键提取*/
class ExtractController extends BaseController
{


    private static $Key="BAE97B4A9CF980BA965887F6C03EC0E2";

    private static $User="EB0CEB8D6FF1B6F9C6F134766DC09EB2";
    /*private static $sendCode="http://58.218.68.158:8086/api/AppWebApi/GetSmsVerCode_ApiBinding";
    private static $checkCode="http://58.218.68.158:8086/api/AppWebApi/CheckVerCode_ApiBinding";
    private static $check_the_balance="http://58.218.68.158:8086/api/AppWebApi/GetProfit_api";
    private static $extract_code="http://58.218.68.158:8086/api/AppWebApi/GetSmsVerCode_ApiWithdraw";
    private static $check_extract_code="http://58.218.68.158:8086/api/AppWebApi/CheckVerCode_ApiWithdraw";
    private static $extract_balance="http://58.218.68.158:8086/api/AppWebApi/Withdraw_api";*/
    private static $sendCode="http://ApiPortal.cmbcrouter.com:8086/api/AppWebApi/GetSmsVerCode_ApiBinding";
    private static $checkCode="http://ApiPortal.cmbcrouter.com:8086/api/AppWebApi/CheckVerCode_ApiBinding";
    private static $check_the_balance="http://ApiPortal.cmbcrouter.com:8086/api/AppWebApi/GetProfit_api";
    private static $extract_code="http://ApiPortal.cmbcrouter.com:8086/api/AppWebApi/GetSmsVerCode_ApiWithdraw";
    private static $check_extract_code="http://ApiPortal.cmbcrouter.com:8086/api/AppWebApi/CheckVerCode_ApiWithdraw";
    private static $extract_balance="http://ApiPortal.cmbcrouter.com:8086/api/AppWebApi/Withdraw_api";
    private static $exchange_rate="http://coin.starbridgechain.com:8080/api/WalletService/GetRecentCoinInfo";


    /*获取汇率*/
    public function getExchangeRate(Request $request){
        //发送绑定验证码
        $rst = Curl::get(self::$exchange_rate,['Content-Type: application/json']);
//        $rst = json_decode($rst,true);

        return $rst;

    }


    /*发送绑定验证码*/
    public function sendBindCode(Request $request){

        $mobile = $request->param('mobile','');     //电话
        $pwd = $request->param('pwd','');           //路由APP的登录密码
        $temp  = [
            'Key'=>self::$Key,
            'User'=>self::$User,
            'Mobile'=>$mobile,
            'Password'=>$pwd,
        ];


        if($mobile==''){
            return jorx(['code' => 201,'msg' => '电话不能为空','e_msg' => 'The phone number cannot be empty']);
        }
        if($pwd==''){
            return jorx(['code' => 201,'msg' => '密码不能为空','e_msg' => 'The password cannot be empty']);
        }

        //发送绑定验证码
        $rst = Curl::post(self::$sendCode,json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);

        if($rst['Header']['Msg']=='ok'){
            return jorx(['code' => 200,'msg' => '验证码发送中！','e_msg' => 'Verification code  sending']);
        }

        if($rst['Header']['ClientErrorCode']==301){
            return jorx(['code' => 301,'msg' => '发送失败！短信1分钟内只能发送1条','e_msg'=>'SMS messages can only be sent in 1 minute!','data'=>$rst['Header']['Msg']]);
        }
        if($rst['Header']['ClientErrorCode']==302){
            return jorx(['code' => 302,'msg' => '发送失败！短信当天最多只能发送5条','e_msg'=>'Messages can only be sent in 5 messages per day!','data'=>$rst['Header']['Msg']]);
        }
        return jorx(['code' => 201,'msg' => $rst['Header']['Msg'],'e_msg'=>'Verification code sending failed!']);

    }

    /*绑定钱包和手机号*/
    public function bindMobile(Request $request){

        $user_id = $request->user['fd_id'];
        $mobile = $request->param('mobile','');     //电话
        $code = $request->param('code','');           //验证码
        $walletId = $request->param('walletId','');           //钱包id
        if($mobile==''){
            return jorx(['code' => 201,'msg' => '电话不能为空','e_msg' => 'The phone number cannot be empty']);
        }

        if($code==''){
            return jorx(['code' => 201,'msg' => '验证码不能为空','e_msg' => 'The Verification code cannot be empty']);
        }
        if($walletId==''){
            return jorx(['code' => 201,'msg' => '钱包id不能为空','e_msg' => 'The wallet id cannot be empty']);
        }
        $temp  = [
            'Key'=>self::$Key,
            'User'=>self::$User,
            'Mobile'=>$mobile,
            'VerCode'=>$code,
        ];

        //验证验证码是否正确,正确以后再进行绑定
        $rst = Curl::post(self::$checkCode,json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);
        if($rst['Header']['Msg']=='ok'){       //手机号绑定钱包

            //判断用户是否是当前用户
            $purse_userid=Db::table('wallet_purse')
                ->field('fd_userId')
                ->where(['fd_id'=>$walletId])
                ->find();

            if($user_id!=$purse_userid['fd_userId']){
                return jorx(['code' => 201,'msg' => '绑定失败,钱包不属于当前用户！！！','e_msg'=>'Binding  failure,the wallet does  not belong to the current user!']);
            }
            //判断钱包是否是特殊钱包
            $wkj_user_coinsbc=Db::table('wkj_user_coinsbc')
                ->field('type')
                ->where(['userid'=>$walletId])
                ->find();
            if($wkj_user_coinsbc['type']!=2){
                return jorx(['code' => 201,'msg' => '绑定失败,当前钱包不是特殊钱包,请重新获取！！！','e_msg'=>'Binding failure,the wallet is not a special wallet,Please reacquire!']);
            }

            //判断钱包是否已绑定
            $info=Db::table('profit_purse_mobile')->where(['mobile_num'=>$mobile,'type'=>1])->find();
            if($info!=null){
                return jorx(['code' => 201,'msg' => '当前钱包与账号已绑定！','e_msg'=>'The wallet has been bound']);
            }
            //绑定
            $re=Db::table('profit_purse_mobile')->insert([
                'user_id'=>$user_id,
                'purse_id'=>$walletId,
                'mobile_num'=>$mobile,
                'type'=>1,
            ]);

            if($re){
                return jorx(['code' => 200,'msg' => '绑定成功','e_msg'=>'Binding success!']);
            }else{
                return jorx(['code' => 200,'msg' => '绑定失败,请重试！','e_msg'=>'Binding failure,success!Please try again!']);
            }



        }
        return jorx(['code' => 201,'msg' => $rst['Header']['Msg'],'e_msg'=>'failed']);

    }

    /*手机号查路由器余额列表*/
    public function balanceMobile(Request $request){
        $user_id = $request->user['fd_id'];
        $limit = $request->param('limit');
        $page = $request->param('page');
        if($limit ==''|| (int)$limit<=0)
            $limit=10;
        if($page==''|| (int)$page<=0)
            $page=1;
        $purse=Db::table('profit_purse_mobile')
            ->field('mobile_num')
            ->where(['user_id'=>$user_id])
            ->where(['type'=>1])
            ->limit(((int)$page-1)*$limit,(int)$limit)
            ->select();
        file_put_contents('ww.txt',Db::table('profit_purse_mobile')->getLastSql());
        $total_num=Db::table('profit_purse_mobile')
            ->field('mobile_num')
            ->where(['user_id'=>$user_id])
            ->where(['type'=>1])
            ->count();
        $mobile_list=[];
        foreach($purse as $item){
            $mobile_list[]=$item['mobile_num'];
        }
        $temp  = [
            'Key'=>self::$Key,
            'User'=>self::$User,
            'MobileList'=>$mobile_list,
        ];

        $rst = Curl::post(self::$check_the_balance,json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);
        return jorx(['code' => 200,'msg' => '成功！','e_msg'=>'Success!','total'=>$total_num,'data'=>$rst['Body']['Data']]);
    }


    /*发送提取验证码*/
    public function sendExtractCode(Request $request){
        $mobile = $request->param('mobile','');     //电话
        $user_id = $request->user['fd_id'];
        if($mobile==''){
            return jorx(['code' => 201,'msg' => 'mobile号码不能为空！','e_msg'=>'The pthone number  cannot be empty!']);
        }

        $re=Db::table('profit_purse_mobile')->where(['mobile_num'=>$mobile,'type'=>1])->find();
        if($user_id!=$re['user_id']){
            return jorx(['code' => 201,'msg' => '当前用户和绑定用户不匹配,请确认！','e_msg'=>'The Current user does not match ths binding user,please confirm!']);
        }
        $temp  = [
            'Key'=>self::$Key,
            'User'=>self::$User,
            'Mobile'=>$mobile,
        ];
        $rst = Curl::post(self::$extract_code,json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);
        if($rst['Header']['Msg']=='ok'){       //手机号绑定钱包
            return jorx(['code' => 200,'msg' => '提取验证码发送中！','e_msg'=>'Verification code  sending! ']);
        }
        if($rst['Header']['ClientErrorCode']==301){
            return jorx(['code' => 301,'msg' => '发送失败！短信1分钟内只能发送1条','e_msg'=>'SMS messages can only be sent in 1 minute!','data'=>$rst['Header']['Msg']]);
        }
        if($rst['Header']['ClientErrorCode']==302){
            return jorx(['code' => 302,'msg' => '发送失败！短信当天最多只能发送5条','e_msg'=>'Messages can only be sent in 5 messages per day!','data'=>$rst['Header']['Msg']]);
        }
        return jorx(['code' => 201,'msg' => $rst['Header']['Msg'],'e_msg'=>'Verification code sending failed!',]);
    }


    /*一键提取余额*/
    public function extractBalance(Request $request){
        $mobile = $request->param('mobile','');     //电话
        $VerCode = $request->param('vercode','');     //提取验证码
        $user_id = $request->user['fd_id'];
/*        $temp  = [
            'Key'=>self::$Key,
            'User'=>self::$User,
            'Mobile'=>$mobile,
            'VerCode'=>$VerCode,
        ];
        $rst = Curl::post(self::$check_extract_code,json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);

        if($rst['Header']['Msg']=='ok'){       //余额提取*/
            $temp1  = [
                'Key'=>self::$Key,
                'User'=>self::$User,
                'Mobile'=>$mobile,
            ];
            $rst1 = Curl::post(self::$extract_balance,json_encode($temp1),['Content-Type: application/json']);
            $rst1 = json_decode($rst1,true);
            if($rst1['Header']['Msg']=='ok'){
                $re=Db::table('profit_purse_mobile')->where(['mobile_num'=>$mobile,'user_id'=>$user_id])->find();
                $sbc=Db::table('wkj_user_coinsbc')->where('userid',$re['purse_id'])->find();

                $order_no=$rst1['Body']['Data']['OrderID'];     //order_no
                if($rst1['Body']['Data']['DetailList']==[]){
                    $sdt_add=0;
                }else{
                    $sdt_add=$rst1['Body']['Data']['DetailList'][0]['TotalValue']; //提现数量
                }
                $temp_sdt=bcadd($sbc['sbc'],$sdt_add,15);       //所有数量

                Db::startTrans();
                try {
                    //sbc增加余额
                    Db::table('wkj_user_coinsbc')->where('userid',$re['purse_id'])->setField('sbc',$temp_sdt);
                    //wallet_user增加余额
                    $user_sbc=Db::table('wallet_user')->where(['fd_id'=>$user_id])->value('fd_sbcNums');
                    $add_user_sdt=bcadd($sdt_add,$user_sbc,8);
                    Db::table('wallet_user')->where(['fd_id'=>$user_id])->setField('fd_sbcNums',$add_user_sdt);

                    //路由器提现记录
                    Db::table('wallet_recharge')->insert([
                        'fd_routerUUid' => $order_no,
                        'fd_walletId'   => $re['purse_id'],
                        'fd_wtuserId'   => $user_id,
                        'fd_sbcNums'    => $sdt_add,
                        'fd_status'     => 0,
                        'fd_createDate' => date('Y-m-d H:i:s')
                    ]);

                    Db::commit();
                    return jorx(['code' => 200,'msg' => '提取成功,金额: '.$sdt_add,'e_msg'=>'Extraction of success,Extraction of: '.$sdt_add]);
                } catch (\Exception $e){
                    Db::rollback();
                    return jorx(['code' => 201,'msg' => '提取失败','e_msg'=>'Extraction of failure ','error' => $e->getMessage()]);
                }


            }
            return jorx(['code' => 201,'msg' => $rst1['Header']['Msg'],'e_msg'=>'verification failed ']);

     /*   }

        return jorx(['code' => 201,'msg' => '发送提取验证失败！','e_msg'=>'verification failed ','data'=>$rst['Header']['Msg']]);*/

    }

    /*删除钱包*/
    public function deletePurse(Request $request){
        $purse_id = $request->param('purse_id','');     //钱包id
        $user_id = $request->user['fd_id'];
        $where['userid']=$purse_id;



        $sdt=Db::table('wkj_user_coinsbc')->where($where)->find();
        $purse=Db::table('wallet_purse')->where(['fd_id'=>$purse_id])->find();

        if($user_id!=$purse['fd_userId']){
            return jorx(['code' => 201,'msg' => '删除失败,钱包不属于当前用户！！！','e_msg'=>'Delete failed,the wallet does not belong to the current user!']);
        }
        if($sdt['type']==2){
            return jorx(['code' => 201,'msg' => '删除失败,当前钱包属于特殊钱包！！！','e_msg'=>'Delete failed,the wallet is a special wallet!']);
        }
        if($sdt['sbc']!=0||$sdt['sbcd']!=0){
            return jorx(['code' => 201,'msg' => '删除失败,钱包中还有余额！！！','e_msg'=>'Delete failed,the wallet still has some money!']);
        }

        Db::startTrans();
        try {

            $true_sbc=Db::table('wkj_user_coinsbc')->where(['userid'=>$purse_id])->delete();
            $true_purse=Db::table('wallet_purse')->where(['fd_id'=>$purse_id])->delete();

            Db::commit();
            return jorx(['code' => 200,'msg' => '钱包删除成功！','e_msg'=>'Deleted successfully!']);
        } catch (\Exception $e){
            Db::rollback();
            return jorx(['code' => 201,'msg' => '删除失败','e_msg'=>'Delete failed!','error' => $e->getMessage()]);
        }



    }

    /*删除路由APP绑定关系*/
    public function deleteMobile(Request $request){

        $mobile = $request->param('mobile','');     //钱包id
        $user_id = $request->user['fd_id'];
        $where['user_id']=$user_id;
        $where['mobile_num']=$mobile;
        $where['type']=1;



        $mobilePurse=Db::table('profit_purse_mobile')->where($where)->find();
        if($mobilePurse==null){
            return jorx(['code' => 201,'msg' => '删除失败,当前记录不存在！','e_msg'=>'Record does not exist!']);
        }

        $re=Db::table('profit_purse_mobile')->where($where)->setField('type',2);
        if($re){
            return jorx(['code' => 200,'msg' => '删除成功！','e_msg'=>'Deleted successfully!']);
        }else{
            return jorx(['code' => 201,'msg' => '删除失败！','e_msg'=>'Deleted failed，please try again!']);
        }


    }



}




