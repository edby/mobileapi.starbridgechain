<?php


namespace app\wallet\controller;


use app\wallet\model\Order;
use app\wallet\model\Purse;
use app\wallet\service\Email;
use curl\Curl;
use think\Config;
use think\Db;
use think\Request;
use think\helper\Str;
use think\helper\Hash;
use app\wallet\model\User;


use app\wallet\model\Logrecord;
use redis\Redis;
use think\Exception;

/*分红
 * */
class NewcheckinController extends BaseController
{
    private static $Key="BAE97B4A9CF980BA965887F6C03EC0E2";
    private static $User="EB0CEB8D6FF1B6F9C6F134766DC09EB2";
    //private static $bind_sn="http://58.218.68.15:8086/api/AppWebApi/Router_NewActivity_new";
    private static $bind_sn="http://ApiPortal.cmbcrouter.com:8086/api/AppWebApi/Router_NewActivity_new";

    /*绑定sn*/
    public function bindSn(Request $request){
        $mobile = $request->param('mobile','');
        $sn = $request->param('sn','');
        $pwd = $request->param('pwd','');
        $user_id = $request->user['fd_id'];
        $new_user=Db::table('profit_newuser_checkin')
                    ->where(['sn'=>$sn])->find();

        if($new_user){
            return jorx(['code' => 201,'msg' => 'SN号已绑定,请确认!','e_msg'=>'The SN number  has been bound!']);
        }
        //$pwd=strtoupper(md5($pwd));
        $temp  = [
            'Key'=>self::$Key,
            'User'=>self::$User,
            'Mobile'=>$mobile,
            'Password'=>$pwd,
            'SN'=>$sn,
        ];


        //验证sn是否正确
        $rst = Curl::post(self::$bind_sn,json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);

        if($rst['Header']['Msg']=='ok'){       //余额提取

                Db::table('profit_newuser_checkin')
                ->insert([
                    'user_id'=>$user_id,
                    'uuid'=>$rst['Body']['Data']['UUID'],
                    'sn'=>$rst['Body']['Data']['SN'],
                    'mac'=>$rst['Body']['Data']['MAC'],
                    'mobile_num'=>$mobile,
                    'batch'=>$rst['Body']['Data']['No'],
                    'sdt'=>1,
                    'start_time'=>$rst['Body']['Data']['StartTime'],
                    'end_time'=>$rst['Body']['Data']['EndTime'],
                    'create_time'=>date('Y-m-d H:i:s'),
                    'unix_time'=>strtotime(date('Y-m-d')),

                ]);


            return jorx(['code' => 200,'msg' => '绑定成功!','e_msg'=>'Binding success!']);
        }

        $err=[
           '5'=>'User does not exist!',
           '101'=>'User and password do not match!',
           '203'=>'Router is not bound!',
           '204'=>'Router does not exist!',
           '652'=>'Router does not meet the conditions!',
           '653'=>'Router does not match!',
        ];

        return jorx(['code' => $rst['Header']['ClientErrorCode'],'msg' => $rst['Header']['Msg'],'e_msg'=>$err[$rst['Header']['ClientErrorCode']]]);


    }

    /*登录*/
    public function login(Request $request){
        $mobile = $request->param('mobile','');
        $pwd = $request->param('pwd','');

        //$pwd=strtoupper(md5($pwd));
        $temp  = [
            'Key'=>self::$Key,
            'User'=>self::$User,
            'Mobile'=>$mobile,
            'Password'=>$pwd,
        ];

        //验证sn是否正确
        $rst = Curl::post(self::$bind_sn,json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);
        if($rst['Header']['Msg']=='ok'){       //余额提取
            $data['mobile']=$mobile;
            if($rst['Body']['Data']==[]){
                $data['ssn']=[];
                return jorx(['code' => 200,'msg' => '登录成功!','data'=>$data]);
            }
            $data['sn']=$rst['Body']['Data'];
            $temp=[];
            foreach($data['sn'] as $v){
                    $temp[]=$v['SN'];
            }
            $where['sn']=array('in',$temp);
            $sns=Db::table('profit_newuser_checkin')->field("sn")->where($where)->select();
            if(count($sns)==0){
                foreach($data['sn'] as $itm){
                    $data['ssn'][]=[
                        "SNAR_ID"=>$itm["SNAR_ID"],
                        "UUID"=>$itm["UUID"],
                        "SN"=>$itm["SN"],
                        "MAC"=>$itm["MAC"],
                        "StartTime"=>$itm["StartTime"],
                        "EndTime"=>$itm["EndTime"],
                        "No"=>$itm["No"],
                        "Status"=>$itm["Status"],
                        "CreateTime"=>$itm["CreateTime"],
                        "check"=>1,
                    ];
                }
                unset($data['sn']);
                return jorx(['code' => 200,'msg' => '登录成功!','data'=>$data]);
            }else{
                $sn_temp=[];
                foreach($sns as $item){
                    $sn_temp[]=$item['sn'];
                }
                foreach($data['sn'] as $kk=>$vv){
                    $data['sn'][$kk]['check']=1;
                    if(in_array($vv['SN'],$sn_temp)){
                        unset($data['sn'][$kk]);
                    }
                }
                if($data['sn']==null){
                    $data['ssn']=[];
                    unset($data['sn']);
                }else{
                    foreach($data['sn'] as $itm){
                        $data['ssn'][]=[
                            "SNAR_ID"=>$itm["SNAR_ID"],
                            "UUID"=>$itm["UUID"],
                            "SN"=>$itm["SN"],
                            "MAC"=>$itm["MAC"],
                            "StartTime"=>$itm["StartTime"],
                            "EndTime"=>$itm["EndTime"],
                            "No"=>$itm["No"],
                            "Status"=>$itm["Status"],
                            "CreateTime"=>$itm["CreateTime"],
                            "check"=>1,
                        ];
                    }
                    unset($data['sn']);
                }


                return jorx(['code' => 200,'msg' => '登录成功!','data'=>$data]);
            }

        }
        if($rst['Header']['Msg']==null){
            return jorx(['code' => 201,'msg' =>'绑定失败','e_msg'=>'Binding failure!']);
        }else{
            return jorx(['code' => 201,'msg' => $rst['Header']['Msg'],'e_msg'=>'Binding failure!']);
        }

    }

    /*绑定数据*/
    public function bindingSn(Request $request){
        return jorx(['code' => 200,'msg' => '活动结束!']);
        die;
        $sn =$request->post('sn/a');       //获取数组
        $mobile =$request->param('mobile');
        $pwd = $request->param('pwd','');
        $temp  = [
            'Key'=>self::$Key,
            'User'=>self::$User,
            'Mobile'=>$mobile,
            'Password'=>$pwd,
        ];

        $rst = Curl::post(self::$bind_sn,json_encode($temp),['Content-Type: application/json']);
        $rst = json_decode($rst,true);
        $uuid_arr=[];       //当前账户的uuid
        if($rst['Header']['Msg']=='ok') {       //余额提取
            $data['mobile'] = $mobile;
            if ($rst['Body']['Data'] == []) {
                $data['ssn'] = [];
                return jorx(['code' => 200, 'msg' => '登录成功!', 'data' => $data]);
            }
            $data['sn'] = $rst['Body']['Data'];
            foreach($data['sn'] as $itm){
                $uuid_arr[]=$itm['UUID'];
            }

        }



        $user_id = $request->user['fd_id'];
        $temp=[];
        Db::startTrans();
        try {
            foreach($sn as $k=>$v){
                if(in_array($v['UUID'],$uuid_arr)){
                    $temp[]=[
                        'user_id'=>$user_id,
                        'uuid'   =>$v['UUID'],
                        'sn'   =>$v['SN'],
                        'mac'   =>$v['MAC'],
                        'mobile_num'   =>$mobile,
                        'batch'   =>$v['No'],
                        'sdt'=>1,
                        'start_time'=>$v['StartTime'],
                        'end_time'=>$v['EndTime'],
                        'create_time'=>date('Y-m-d H:i:s'),
                        'unix_time'=>strtotime(date('Y-m-d')),
                    ];
                }

            }
            Db::table('profit_newuser_checkin')->insertAll($temp);
            Db::commit();
            return jorx(['code' => 200,'msg' => '绑定成功!','e_msg'=>'Binding success!']);
        } catch (\Exception $e){
            Db::rollback();
            return jorx(['code' => 201,'msg' => '批次绑定失败','error' => $e->getMessage()]);
        }



    }


    /*显示分红*/
    public function showDividend(Request $request){
        $batch = $request->param('batch',1810);
        if($batch=='') return jorx(['code' => 201,'msg' => '批次号不能为空!']);
        $batch_setting=Db::table('profit_newuser_setting')->field('batch,add_num')->where(['batch'=>$batch,'type'=>1])->find();
        if($batch_setting==null){
            return jorx(['code' => 201,'msg' => '批次已过期,或者不存在!']);
        }
        //累计分配,今日待分配,昨日已分配
        $info['total']=Db::table('profit_newuser_dividend')->where(['batch'=>$batch_setting["batch"]])->sum('btc_base');  //总累计
        $re=Db::table('profit_newuser_dividend')->field('btc_base')->where(['batch'=>$batch_setting["batch"]])->order('id desc')->limit(2)->select(); //当日待分配

        //已入矿池数量
        $info['total_equipment']=Db::table('profit_newuser_checkin')->where(['batch'=>$batch,'type'=>1])->count();
        //单台收益率
        $num=$batch_setting['add_num']+$info['total_equipment'];       //增加
        $info['total_equipment']=$num;
        $now=time();
        if($num==0){
            $info['rate_of_return']=0;
        }else{
            if($now<1538290800){    //30号15:0:0之前显示累计待分配收益率，之后显示当日收益率
                $info['rate_of_return']=bcdiv($info['total'],$num,10);
            }else{
                $info['rate_of_return']=bcdiv($re[0]['btc_base'],$num,10);
            }
        }


        if($now>1538290800){       //30好之前,页面显示累计待分配,以后显示昨日待分配
            $info['type']=1;
            $info['today']=$re[0]['btc_base'];
            if($now<1538323200){      //1号之前显示累计待分配，1号之后显示昨日待分配
                $info['yesterday']=$info['total'];
            }else{
                $info['yesterday']=$re[1]['btc_base'];
            }
        }else{
            $info['today']=0;
            $info['yesterday']=0;
            $info['type']=2;
        }

        return jorx(['code' => 200,'msg' => '获取成功!','data'=>$info]);

    }




    /*显示增加设备数*/
    public function showAdd(Request $request){
        $setting=Db::table('profit_newuser_setting')->field('add_num')->where(['type'=>1,'batch'=>1810])->find();
        return jorx(['code' => 200,'msg' => '成功!','data'=>$setting]);
    }


    /*显示计算最新分红*/
    public function calDividend(Request $request){
        $ip  = $request->ip();
        if($ip!='127.0.0.1')  return jorx(['code' => 202, 'msg' => 'BTC非法的IP访问!']);
        $setting=Db::table('profit_newuser_setting')->where(['type'=>1,'batch'=>1810])->find();
        $temp_num=mt_rand(1,9);     //今日折合上下浮动
        if($temp_num%2==0){
            $stte_num=$setting['floating_down'];
        }else{
            $stte_num=$setting['floating_up'];
        }
        Db::table('profit_newuser_dividend')->insert([
            'btc_base'=>$setting['btc_base']*$stte_num*floatval(0.99.$temp_num),
            'batch'=>$setting['batch'],
            'start_time'=>$setting['start_time'],
            'end_time'=>$setting['end_time'],
            'allocation_time'=>date('Y-m-d',strtotime("-1 day")),
            'create_time'=>date('Y-m-d H:i:s'),
        ]);

        return jorx(['code' => 200,'msg' => '成功!']);

    }


    /*增加分红批次*/
    public function addBatch(Request $request){
        $batch = $request->param('batch','');
        $start_time = $request->param('start_time','');
        $end_time = $request->param('end_time','');
        $btc_base = $request->param('btc_base','');
        if($batch=='') return jorx(['code' => 201,'msg' => '批次名字不能为空!']);
        if($start_time=='') return jorx(['code' => 201,'msg' => '开始时间不能为空!']);
        if($end_time=='') return jorx(['code' => 201,'msg' => '结束时间不能为空!']);
        if($btc_base=='') return jorx(['code' => 201,'msg' => '分红基数不能为空!']);

        Db::startTrans();
        try {
            Db::table('profit_newuser_setting')->insert([
                'batch'=>$batch,
                'start_time'=>$start_time,
                'end_time'=>$end_time,
                'btc_base'=>$btc_base,
                'create_time'=>date('Y-m-d H:i:s'),
            ]);

            Db::table('profit_newuser_dividend')->insert([
                'batch'=>$batch,
                'start_time'=>$start_time,
                'end_time'=>$end_time,
                'btc_base'=>$btc_base,
                'create_time'=>date('Y-m-d H:i:s'),
            ]);

            Db::commit();
            return jorx(['code' => 200,'msg' => '批次添加成功!']);


        } catch (\Exception $e){
            Db::rollback();
            return jorx(['code' => 201,'msg' => '批次添加失败','error' => $e->getMessage()]);
        }

    }


    /*获取所有有效批次号*/
    public function getBatch(Request $request){

            $re=Db::table('profit_newuser_setting')->field('batch')->where(['type'=>1])->select();
            $info=[];
            foreach($re as $v){
                $info[]=$v['batch'];
            }
            return jorx(['code' => 200,'msg' => '批次获取成功!','data'=>$info]);
    }


    /*获取所有有效批次号*/
    public function getBatchList(Request $request){
        $info=Db::table('profit_newuser_setting')->select();
        return jorx(['code' => 200,'msg' => '批次获取成功!','data'=>$info]);
    }


    /*活动时间倒计时*/
    public function getTime(Request $request){
        $batch = $request->param('batch','');
        if($batch=='') return jorx(['code' => 201,'msg' => '批次不能为空!']);
        $unix_time=Db::table('profit_newuser_setting')->value('end_time');
        $now=time();
        $info['time']=strtotime($unix_time)-$now;      //2018/9/30 15:0:0
        if($info['time']<=0) $info['time']=0;

        return jorx(['code' => 200,'msg' => '获取倒计时成功!','data'=>$info]);
    }


    /*获取个人参加信息*/
    public function getInfo(Request $request){
        $user_id = $request->user['fd_id'];
        $info['count']=Db::table('profit_newuser_checkin')->where(['user_id'=>$user_id,'type'=>1])->count();
        $info['SDT1810']=$info['count']*2000;
        $info['btc']=Db::table('profit_newuser_btc')->where(['user_id'=>$user_id])->sum('btc');
        $info['inpool']=Db::table('profit_newuser_checkin')->field('sn,mobile_num,create_time,sdt')->where(['user_id'=>$user_id,'type'=>1])->select();
        $info['dividend']=Db::table('profit_newuser_btc')->where(['user_id'=>$user_id,'type'=>1])->select();
        return jorx(['code' => 200,'msg' => '个人信息获取成功!','data'=>$info]);
    }


    /*给所有1810用户分红*/
/*    public function btcDividend(Request $request){
        die;
        $ip  = $request->ip();
        if($ip!='127.0.0.1')  return jorx(['code' => 202, 'msg' => 'BTC非法的IP访问!']);
        $batch=Db::table('profit_newuser_setting')->where(['type'=>1])->value('batch');
        Db::startTrans();
        try {

            //总共要分的btc
            $count=Db::table('profit_newuser_btc')->count();
            if($count==0){
                $total_btc=Db::table('profit_newuser_dividend')->where(['batch'=>$batch])->sum('btc_base');   //30号
            }else{
                $total_btc=Db::table('profit_newuser_dividend')->where(['batch'=>$batch])->order('create_time desc')->value('btc_base');  //30号以后
            }

            //sdt1810总额
            $sdt1810=Db::table('profit_newuser_checkin')->where(['type'=>1])->count();
            $add_num=Db::table('profit_newuser_setting')->where(['type'=>1])->value('add_num');
            $total_sdt1810=$add_num+$sdt1810;

            //获取当前时间
            $now=time();
            if($now<1538290800){    //30分红只给29号之前绑定的用户分
                $user=Db::table('profit_newuser_checkin')->field('user_id')->where(['unix_time'=>['elt',1538150400]])->where(['batch'=>1810,'type'=>1])->select();
            }else{
                $user=Db::table('profit_newuser_checkin')->field('user_id')->where(['batch'=>1810,'type'=>1])->select();
            }
            //获取所有用户
            $arr=[];
            foreach($user as $k=>$v){
                if(!in_array($v['user_id'],$arr)){
                    $arr[]=$v['user_id'];
                }
            }
            $total=[];
            foreach($arr as $v){
                $count=Db::table('profit_newuser_checkin')->field('user_id')->where(['batch'=>1810,'type'=>1,'user_id'=>$v])->count();
                $re=bcmul($total_btc,bcmul(bcdiv(1,$total_sdt1810,8),$count,8),8);      //分红

                $total[]=[
                    'user_id'=>$v,
                    'count'=>$count,
                    'btc'=>$re,
                    'type'=>1,
                    'unix_time'=>strtotime(date('Y-m-d')),
                    'create_time'=>date('Y-m-d H:i:s'),
                ];
            }
            Db::table('profit_newuser_btc')->insertAll($total);

            //8号以后每天发btc,直接入账户
            if($now>1538928000){
                foreach($total as $v){
                    $btc=Db::table('wkj_user_coinbtc')->where(['userid'=>$v['user_id']])->value('btc');
                    $add_btc=bcadd($btc,$v['btc'],8);
                    Db::name('btc_bak')->insert([
                        'user_id'=>$v['user_id'],
                        'user_ture_btc'=>$v['btc'],
                        'btc'=>$btc,
                        'btcs'=>$add_btc,
                        'time'=>date('Y-m-d H:i:s')
                    ]);
                    Db::table('wkj_user_coinbtc')->where(['userid'=>$v['user_id']])->setInc('btc',$v['btc']);
                }
            }


            Db::commit();

            $email[]='abdi1006@foxmail.com';
            $email[]='1477563131@qq.com';
            $btc_num=Db::table('profit_newuser_btc')->where(['unix_time'=>strtotime(date('Y-m-d'))])->sum('btc');
            foreach($email as $v){
                $title = '新设备---BTC分红成功';                //主题
                $body =date('Y-m-d H:i:s') . '新设备---BTC分红成功！用户金额：'.$btc_num;
                Email::sendEmail2($title, [$v], $body);
            }

            return jorx(['code' => 200, 'msg' => '分红成功!']);
        } catch (\Exception $e){
            Db::rollback();
            return jorx(['code' => 201,'msg' => '失败','error' => $e->getMessage()]);
        }


    }*/


    /*第一次分红后7号，将btc分发到用户账号*/
    /*public function pushAccount(Request $request){
        die;
        $ip  = $request->ip();
        if($ip!='127.0.0.1')  return jorx(['code' => 202, 'msg' => 'BTC非法的IP访问!']);die;
        Db::startTrans();
        try {

            $user=Db::table('profit_newuser_btc')->field('user_id,btc')->select();
            foreach($user as $v){
                $btc=Db::table('wkj_user_coinbtc')->where(['userid'=>$v['user_id']])->value('btc');
                $add_btc=bcadd($btc,$v['btc'],10);
                //Db::table('wkj_user_coinbtc')->where(['userid'=>$v['user_id']])->setInc('btc',$v['btc']);
                Db::table('profit_newuser_btc_pre')->insert([
                    'user_id'=>$v['user_id'],
                    'add_btc'=>$v['btc'],
                    'new_btc'=>$add_btc,
                    'create_time'=>date('Y-m-d H:i:s'),
                ]);

            }

            Db::commit();
            return jorx(['code' => 200, 'msg' => '分红成功!']);
        } catch (\Exception $e){
            Db::rollback();
            return jorx(['code' => 201,'msg' => '失败','error' => $e->getMessage()]);
        }


    }*/



}




