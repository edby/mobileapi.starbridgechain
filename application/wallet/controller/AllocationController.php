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
class AllocationController extends BaseController
{



    /*
     * 每天13点456钱包向789转账（解封）
     * */

    public function transfer(){
        //获取当日应当发放的sdt数量
        $aa= date('Y-m-d H:i:s');
        $where['SSYS_StartTime']=['elt',$aa];
        $where['SSYS_EndTime']=['egt' ,$aa];
        $count=Db::table('Profit_sdtyearSetting')->where($where)->find();//获取当日的sdt发放量
        $num=$count['SSYS_SDTAmount']*(1/3)*(1/3);

        $arr=[
            '0x000a5b9fc190cff707980a8f4def4000sgdgay04'=>'0x000a5b9fc190cff707980a8f4def4000sgdgay07',
            '0x000a5b9fc190cff707980a8f4def4000sgdgay05'=>'0x000a5b9fc190cff707980a8f4def4000sgdgay08',
            '0x000a5b9fc190cff707980a8f4def4000sgdgay06'=>'0x000a5b9fc190cff707980a8f4def4000sgdgay09',
        ];
        foreach($arr as $k=>$v){
            $this->walletTransfer($k,$v,$num);      //转账
        }

        $this->addSdt(); //调用sdt虚增
        return jorx(['code' => 200,'msg' => '456转账成功,btc分红成功,sdt增加成功!']);

    }

    private  function walletTransfer($begin_walletAdr,$end_walletAdr,$num)
    {

        $user=Db::table('wallet_user')->where(['fd_userName'=>'superUser'])->find();
        if(empty($begin_walletAdr)) {
            echo "转账钱包不存在";
            die;
        } else {
            $begin = Db::name('wallet_purse')->where('fd_walletAdr' , $begin_walletAdr)->find();


            if($begin==null) {
                echo "转账钱包不存在";
                die;
            }
        }
        if(empty($end_walletAdr)) {

            echo "收账钱包不存在";
            die;
        } else {
            $end = Db::name('wallet_purse')->where('fd_walletAdr' , $end_walletAdr)->find();
            if(empty($end)) {
                echo "收账钱包不存在";
                die;
            }
        }
        if($num <= 0) {
            echo "请确认转账金额";
            die;
        }
        $sbc = Db::name('wkj_user_coinsbc')->where('userid' , $begin['fd_id'])->value('sbc');
        if($num > $sbc) {
            echo "余额不足";
            die;
        }

        $fee = Db::table('wallet_fee')->where('fd_type' ,'sdt-out')->value('fd_fee');

        $aa=bcsub($sbc,$num,10);
        $begin_re=Db::name('wkj_user_coinsbc')->where('userid' , $begin['fd_id'])->setField('sbc',$aa);
        $begin_user = Db::name('wallet_user')->where('fd_id' , $begin['fd_userId'])->find();
        $sbcs = bcsub($begin_user['fd_sbcNums'],$num,10);
        Db::name('wallet_user')->where('fd_id' , $begin['fd_userId'])->setField('fd_sbcNums',$sbcs);
        if($begin_re)
        {
            $end_re=Db::name('wkj_user_coinsbc')->where('userid' , $end['fd_id'])->value('sbc');
            $bb=bcadd($end_re,$num,10);
            $end_user = Db::name('wallet_user')->where('fd_id' , $end['fd_userId'])->find();
            $sbcs1 = bcsub($end_user['fd_sbcNums'],$num,10);
            Db::name('wallet_user')->where('fd_id' , $end['fd_userId'])->setField('fd_sbcNums',$sbcs1);
            $en_re=Db::name('wkj_user_coinsbc')->where('userid' , $end['fd_id'])->setField('sbc',$bb);
            if($en_re)
            {
                $order_no = createOrderNo();
                $after_sbc = Db::name('wkj_user_coinsbc')->where('userid' , $user['fd_id'])->value('sbc');
                $ins_data['order_no'] = $order_no;
                $ins_data['fd_begin_walletAdr'] = $begin_walletAdr;
                $ins_data['fd_end_walletAdr'] = $end_walletAdr;
                $ins_data['fd_transfer_sbc'] = $num;
                $ins_data['fd_user_id'] = $user['fd_id'];
                $ins_data['fd_col_user_id'] = $end['fd_userId'];
                $ins_data['fd_before_sbc'] = $sbc;
                $ins_data['fd_after_sbc'] = $after_sbc;
                $ins_data['fd_fee'] = $fee;
                $ins_data['created_time'] = date('Y-m-d H:i:s');
                Db::name('wallet_transfer_log')->insert($ins_data);
            }

        }


    }


    /*
     *
     * BTC分红
     * */
    public function btcAnalyze(Request $request){
        set_time_limit(0);
        ini_set('memory_limit','3072M');
        $email[]='abdi1006@foxmail.com';
        $email[]='1477563131@qq.com';
        $type_btc=Db::table('profit_syssetting')->value('btc_type');
        if($type_btc==2){
            foreach($email as $v){
                $title = 'BTC分红接口已经关闭';                //主题
                $body =date('Y-m-d H:i:s') . '分红接口已经关闭,如需要开启,请重新开启！！';
                Email::sendEmail2($title, [$v], $body);
            }
            return jorx(['code' => 202,'msg' => '分红接口已经关闭!']);
        }
        $ip  = $request->ip();
        file_put_contents('ips/btcAnalyze_ip.txt',date("Y-m-d H:i:s").': '.$ip.PHP_EOL,FILE_APPEND);
        if($ip!='127.0.0.1') {
            foreach($email as $v){
                $title = '异常ip访问BTC分红接口';                //主题
                $body = $ip . '在' . date('Y-m-d H:i:s') . '非法访问BTC分红接口';
                Email::sendEmail2($title, [$v], $body);
            }
            return jorx(['code' => 202, 'msg' => 'BTC非法的IP访问!']);
        }

        //获取所有sdt流通总额,除去wallet_group_purse中group_id=3的是123456钱包，以及锁仓
        $sql2="select sum(sbc)+sum(sbcd)  as total_sbc  from wkj_user_coinsbc  where userid not in  (select purse_id from wallet_group_purse where group_id=3);";
        $info2=Db::table('wallet_user_virtual')->query($sql2);
        $lock_sql="select sum(lock_sbcNums)  as total_lock  from wallet_user  where lock_sbcNums!=0;";
        $lock_num=Db::table('wallet_user')->query($lock_sql);
        $circulation_total_sdt=bcadd($info2[0]['total_sbc'],$lock_num[0]["total_lock"],5);

        $yesToday= date("Y-m-d",strtotime("-1 day"));//从数据库获取昨日btc手续费
        $yes_Days= (int)date("d",strtotime("-1 day"));//昨天日期 号数

        //获取真是用户+玩客家的快照总和除以24
        $str="select distinct user_id from wallet_group_purse where group_id=2 or group_id=7";
        $re=Db::table('wallet_group_purse')->query($str);
        $sdt_total=0;
        foreach($re as $item){
            $sql="select  sum(ph_sdt) as total_sdt from profit_24hour where ph_days=".$yes_Days." and  ph_userid=".$item['user_id']." ;";
            $aa=Db::table('profit_24hour')->query($sql);
            $sdt_total=bcadd($aa[0]['total_sdt'],$sdt_total,15);
        }
        $true_sdt=bcdiv($sdt_total,24,15);      //用户真实快照sdt总额
        //除2,7,8,3以外
        $our_str="select distinct user_id from wallet_group_purse where group_id=4 or group_id=5 or group_id=8 or group_id=9 or group_id=10 or group_id=11";
        $our_re=Db::table('wallet_group_purse')->query($our_str);
        $our_sdt_total=0;
        foreach($our_re as $item){
            $sql="select  sum(ph_sdt) as total_sdt from profit_24hour where ph_days=".$yes_Days." and ph_userid=".$item['user_id'].";";
            $aa=Db::table('profit_24hour')->query($sql);
            $our_sdt_total=bcadd($aa[0]['total_sdt'],$our_sdt_total,15);
        }
        $our_true_sdt=bcdiv($our_sdt_total,24,15);      //our快照sdt总额

        //除去group_id=3的是123456钱包快照总额,除以24
        $all_str="select distinct user_id from wallet_group_purse where group_id!=3";
        $all_re=Db::table('wallet_group_purse')->query($all_str);
        $all_sdt_total=0;
        foreach($all_re as $item){
            $sql="select  sum(ph_sdt) as total_sdt from profit_24hour where ph_days=".$yes_Days." and ph_userid=".$item['user_id'].";";
            $aa=Db::table('profit_24hour')->query($sql);
            $all_sdt_total=bcadd($aa[0]['total_sdt'],$all_sdt_total,15);
        }
        $all_true_sdt=bcdiv($all_sdt_total,24,15);      //出去3号官方组之前的所有,真实快照sdt总额




        //从数据库获取btc分红基数
        $btc_base=Db::table('profit_syssetting')->value('pss_btcBase');
        $where['addtime'] = ['between' , [strtotime($yesToday.' 00:00:00') , strtotime($yesToday.' 23:59:59')]];
        $fee_buy = Db::name('wkj_trade_logsbc_btc')->where($where)->sum('fee_buy');
        $fee_sell = Db::name('wkj_trade_logsbc_btc')->where($where)->sum('fee_sell');

        //手续费
        $btc_charge=bcadd($fee_buy,$fee_sell,15);

        //(真实用户btc分红比例)真实btc分红数量/真实用户sdt数量
        $proportion=bcdiv($btc_base,$true_sdt,15);

        //计算显示分红btc数量+手续费
        $page_display_btc=bcadd(bcmul($proportion,$all_true_sdt,15),$btc_charge,15);


        /*页面展示btc分红,插入数据库*/
        $str="select * from  profit_todayBase where  date_format(ptb_createTime, '%Y-%m-%d' )='".$yesToday."';";
        $yes=Db::table('profit_todayBase')->query($str);
        if(count($yes)!=0){
            foreach($email as $v) {
                $title = '第二次访问分红红接口';                //主题
                $body = $ip . '在' . date('Y-m-d H:i:s') . '第二次访问BTC分红红接口';
                Email::sendEmail2($title, [$v], $body);
            }
            return jorx(['code' => 202,'msg' => '一天只能访问一次!']);
        }else{
            $two_yesToday= date("Y-m-d",strtotime("-2 day"));   //
            $str_temp="select * from  profit_todayBase where  date_format(ptb_createTime, '%Y-%m-%d' )='".$two_yesToday."';";
            $yes_temp=Db::table('profit_todayBase')->query($str_temp);
            Db::table('profit_todayBase')->insert([
                'yesterday_true_accumulated'=>$page_display_btc,
                'yesterday_accumulated'=>$yes_temp[0]['yesterday_accumulated'],
                'true_sdt'=>$true_sdt,
                'our_true_sdt'=>$our_true_sdt,
                'all_true_sdt'=>$all_true_sdt,
                'total_sdt'=>$circulation_total_sdt,
                'ptb_createTime'=>$yesToday,
            ]);
        }

        //获取需要分红的账户(真实用户,2,6,7)
        $true_user="select distinct user_id from wallet_group_purse where group_id=2  or group_id=7";
        $true_account=Db::table('wallet_group_purse')->query($true_user);
        //获取our需要分红的账户(4,5,8,9,10,11)
        $our_user="select distinct user_id from wallet_group_purse where group_id=4 or group_id=5 or group_id=8 or group_id=9 or group_id=10 or group_id=11";
        $our_account=Db::table('wallet_group_purse')->query($our_user);



        Db::startTrans();
        try {

            $true_btc=bcadd($btc_base,$btc_charge,15);      //true_user allocation btc
            $our_btc=bcsub($page_display_btc,$true_btc,15); //our_user allocation btc
            //true_user
            foreach($true_account as $k=>$v){

                //查询每个用户24小时持有sdt的总和
                $str_temp="select sum(ph_sdt) as total_sdt  from profit_24hour where  ph_days=".$yes_Days." and ph_userid=".$v['user_id'].";";
                $user_sdt_total=Db::name('profit_24hour')->query($str_temp);
                $user_onehour_avg=bcdiv($user_sdt_total[0]['total_sdt'],24,15);       //The average amount of sdt per person per hour

                $user_proportion=bcdiv($user_onehour_avg,$true_sdt,10);   //每个人的分红比例(每个人每小时的平均值除以总额)

                $user_ture_btc=bcmul($true_btc,$user_proportion,10);  //每个人应该分的btc数量

                $btc_num=Db::name('wkj_user_coinbtc')->where(['userid'=>$v['user_id']])->value('btc');
                $btcs = bcadd($user_ture_btc,$btc_num,10);
                /*测试*/
            /*    if($v['user_id']==1419){
                    file_put_contents("aa.txt",$v['user_id']);
                    sleep(2*60);
                }*/

                /*用户记录表*/
                if($user_ture_btc>0){
                    Db::name('btc_bak')->insert([
                        'user_id'=>$v['user_id'],
                        'user_ture_btc'=>$user_ture_btc,
                        'btc'=>$btc_num,
                        'btcs'=>$btcs,
                        'time'=>date('Y-m-d H:i:s')
                    ]);
                    $re=Db::name('wkj_user_coinbtc')->where(['userid'=>$v['user_id']])->setInc('btc',$user_ture_btc);
                }

                $data=[
                    'pa_userid'=>$v['user_id'],
                    'pa_type'=>1,
                    'pa_btcAmount'=>$user_ture_btc,
                    'pa_createTime'=>date('Y-m-d H:i:s'),
                ];
                //插入排行表
                $total_btc=Db::table('profit_rankings')->where(['pa_userid'=>$v['user_id'],'pa_type'=>1])->value('pa_total');
                if($total_btc){
                    Db::table('profit_rankings')->where(['pa_userid'=>$v['user_id']])->setField('pa_total',bcadd($total_btc,$user_ture_btc,8));
                }else{
                    Db::table('profit_rankings')->insert([
                        'pa_userid'=>$v['user_id'],
                        'pa_total'=>$user_ture_btc,
                        'pa_type'=>1,
                        'pa_createTime'=>date('Y-m-d H:i:s'),
                    ]);
                }
                Db::table('profit_allocation')->insert($data);

            }
            //our_user
            foreach($our_account as $k=>$v){

                //查询每个用户24小时持有sdt的总和
                $str_temp="select sum(ph_sdt) as total_sdt  from profit_24hour where ph_days=".$yes_Days." and ph_userid=".$v['user_id'].";";
                $our_user_sdt_total=Db::name('profit_24hour')->query($str_temp);
                $our_user_onehour_avg=bcdiv($our_user_sdt_total[0]['total_sdt'],24,15);       //计算每个人每小时sdt的平均值

                $our_user_proportion=bcdiv($our_user_onehour_avg,$our_true_sdt,15);   //每个人的分红比例(每个人每小时的平均值除以总额)
                $our_user_ture_btc=bcmul($our_btc,$our_user_proportion,15);  //每个人应该分的btc数量

                $btc_num=Db::name('wkj_user_coinbtc')->where(['userid'=>$v['user_id']])->value('btc');
                $btcs = bcadd($our_user_ture_btc,$btc_num,10);

                //$re=Db::name('wkj_user_coinbtc')->where(['userid'=>$v['user_id']])->setField('btc',$btcs);
                Db::name('btc_bak_our')->insert([
                    'user_id'=>$v['user_id'],
                    'our_ture_btc'=>$our_user_ture_btc,
                    'btc'=>$btc_num,
                    'btcs'=>$btcs,
                    'time'=>date('Y-m-d H:i:s')
                ]);

                $data=[
                    'pa_userid'=>$v['user_id'],
                    'pa_type'=>1,
                    'pa_btcAmount'=>$our_user_ture_btc,
                    'pa_createTime'=>date('Y-m-d H:i:s'),
                ];
                Db::table('profit_allocation')->insert($data);

            }



            Db::commit();

            //分红成功后发送邮件
            foreach($email as $v) {
                $title = 'BTC分红成功';                //主题
                $body = $ip . '在' . date('Y-m-d H:i:s') . ':  BTC分红成功---分红数量：' . $true_btc;
                Email::sendEmail2($title, [$v], $body);
            }
            //销毁变量释放内存
            unset($true_account);
            unset($our_account);

            $this->dividend($ip);   //调用SDT分红

            return jorx(['code' => 200,'msg' => 'SDT和BTC分红成功']);

        } catch (\Exception $e){
            Db::rollback();
            return jorx(['code' => 402,'msg' => '分红失败','error' => $e->getMessage()]);
        }


    }


    /*
     * 分红预执行
     * */
    public function preBtcAnalyze(Request $request){
        set_time_limit(0);
        ini_set('memory_limit','3072M');
        $ip  = $request->ip();
        //获取所有sdt流通总额,除去wallet_group_purse中group_id=3的是123456钱包
        $sql2="select sum(sbc)+sum(sbcd)  as total_sbc  from wkj_user_coinsbc  where userid not in  (select purse_id from wallet_group_purse where group_id=3);";
        $info2=Db::table('wallet_user_virtual')->query($sql2);
        $circulation_total_sdt=$info2[0]['total_sbc'];

        $yesToday= date("Y-m-d",strtotime("-1 day"));//从数据库获取昨日btc手续费
        $yes_Days= (int)date("d",strtotime("-1 day"));//昨天日期

        //获取真是用户+木鸡+玩客家的快照总和除以24
        $str="select distinct user_id from wallet_group_purse where group_id=2   or group_id=7";
        $re=Db::table('wallet_group_purse')->query($str);
        $sdt_total=0;
        foreach($re as $item){
            $sql="select  sum(ph_sdt) as total_sdt from profit_24hour where ph_days=".$yes_Days." and  ph_userid=".$item['user_id']." ;";
            $aa=Db::table('profit_24hour')->query($sql);
            $sdt_total=bcadd($aa[0]['total_sdt'],$sdt_total,15);
        }
        $true_sdt=bcdiv($sdt_total,24,15);      //用户真实快照sdt总额
        //除2,7,8,3以外
        $our_str="select distinct user_id from wallet_group_purse where group_id=4 or group_id=5 or group_id=8 or group_id=9 or group_id=10 or group_id=11";
        $our_re=Db::table('wallet_group_purse')->query($our_str);
        $our_sdt_total=0;
        foreach($our_re as $item){
            $sql="select  sum(ph_sdt) as total_sdt from profit_24hour where ph_days=".$yes_Days." and ph_userid=".$item['user_id'].";";
            $aa=Db::table('profit_24hour')->query($sql);
            $our_sdt_total=bcadd($aa[0]['total_sdt'],$our_sdt_total,15);
        }
        $our_true_sdt=bcdiv($our_sdt_total,24,15);      //our快照sdt总额

        //除去group_id=3的是123456钱包快照总额,除以24
        $all_str="select distinct user_id from wallet_group_purse where group_id!=3";
        $all_re=Db::table('wallet_group_purse')->query($all_str);
        $all_sdt_total=0;
        foreach($all_re as $item){
            $sql="select  sum(ph_sdt) as total_sdt from profit_24hour where ph_days=".$yes_Days." and ph_userid=".$item['user_id'].";";
            $aa=Db::table('profit_24hour')->query($sql);
            $all_sdt_total=bcadd($aa[0]['total_sdt'],$all_sdt_total,15);
        }
        $all_true_sdt=bcdiv($all_sdt_total,24,15);      //出去3号官方组之前的所有,真实快照sdt总额

        //从数据库获取btc分红基数
        $btc_base=Db::table('profit_syssetting')->value('pss_btcBase');
        $where['addtime'] = ['between' , [strtotime($yesToday.' 00:00:00') , strtotime($yesToday.' 23:59:59')]];
        $fee_buy = Db::name('wkj_trade_logsbc_btc')->where($where)->sum('fee_buy');
        $fee_sell = Db::name('wkj_trade_logsbc_btc')->where($where)->sum('fee_sell');

        //手续费
        $btc_charge=bcadd($fee_buy,$fee_sell,15);

        //(真实用户btc分红比例)真实btc分红数量/真实用户sdt数量
        $proportion=bcdiv($btc_base,$true_sdt,15);

        //计算显示分红btc数量+手续费
        $page_display_btc=bcadd(bcmul($proportion,$all_true_sdt,15),$btc_charge,15);


        /*页面展示btc分红,插入数据库*/
        $str="select * from  profit_todayBase where  date_format(ptb_createTime, '%Y-%m-%d' )='".$yesToday."';";
        $yes=Db::table('profit_todayBase')->query($str);
        if(count($yes)!=0){
            $title = '第二次访问分红红接口';                //主题
            $to = '1477563131@qq.com';           //收件人
            $body = $ip.'在'.date('Y-m-d H:i:s').'第二次访问BTC分红红接口';
            Email::sendEmail2($title,[$to],$body);
            return jorx(['code' => 202,'msg' => '一天只能访问一次!']);
        }else{
            $two_yesToday= date("Y-m-d",strtotime("-2 day"));//从数据库获取昨日btc手续费
            $str_temp="select * from  profit_todayBase where  date_format(ptb_createTime, '%Y-%m-%d' )='".$two_yesToday."';";
            $yes_temp=Db::table('profit_todayBase')->query($str_temp);
            Db::table('profit_todayBase_pre')->insert([
                'yesterday_true_accumulated'=>$page_display_btc,
                'yesterday_accumulated'=>$yes_temp[0]['yesterday_accumulated'],
                'true_sdt'=>$true_sdt,
                'our_true_sdt'=>$our_true_sdt,
                'all_true_sdt'=>$all_true_sdt,
                'total_sdt'=>$circulation_total_sdt,
                'ptb_createTime'=>$yesToday,
            ]);
        }

        //获取需要分红的账户(真实用户,2,6,7)
        $true_user="select distinct user_id from wallet_group_purse where group_id=2  or group_id=7";
        $true_account=Db::table('wallet_group_purse')->query($true_user);
        //获取our需要分红的账户(4,5,8,9,10,11)
        $our_user="select distinct user_id from wallet_group_purse where group_id=4 or group_id=5 or group_id=8 or group_id=9 or group_id=10 or group_id=11";
        $our_account=Db::table('wallet_group_purse')->query($our_user);



        Db::startTrans();
        try {

            $true_btc=bcadd($btc_base,$btc_charge,15);      //true_user allocation btc
            $our_btc=bcsub($page_display_btc,$true_btc,15); //our_user allocation btc
            //true_user
            foreach($true_account as $k=>$v){

                //查询每个用户24小时持有sdt的总和
                $str_temp="select sum(ph_sdt) as total_sdt  from profit_24hour where  ph_days=".$yes_Days." and ph_userid=".$v['user_id'].";";
                $user_sdt_total=Db::name('profit_24hour')->query($str_temp);
                $user_onehour_avg=bcdiv($user_sdt_total[0]['total_sdt'],24,15);       //The average amount of sdt per person per hour

                $user_proportion=bcdiv($user_onehour_avg,$true_sdt,15);   //每个人的分红比例(每个人每小时的平均值除以总额)

                $user_ture_btc=bcmul($true_btc,$user_proportion,15);  //每个人应该分的btc数量
                $btc_num=Db::name('wkj_user_coinbtc')->where(['userid'=>$v['user_id']])->value('btc');
                $btcs = bcadd($user_ture_btc,$btc_num,10);
                /*用户记录表*/
                if($user_ture_btc>0){
                    Db::name('btc_bak_pre')->insert([
                        'user_id'=>$v['user_id'],
                        'user_ture_btc'=>$user_ture_btc,
                        'btc'=>$btc_num,
                        'btcs'=>$btcs,
                        'time'=>date('Y-m-d H:i:s')
                    ]);
                }

                $data=[
                    'pa_userid'=>$v['user_id'],
                    'pa_type'=>1,
                    'pa_btcAmount'=>$user_ture_btc,
                    'pa_createTime'=>date('Y-m-d H:i:s'),
                ];
                Db::table('profit_allocation_pre')->insert($data);

            }

            //our_user
            foreach($our_account as $k=>$v){

                //查询每个用户24小时持有sdt的总和
                $str_temp="select sum(ph_sdt) as total_sdt  from profit_24hour where ph_days=".$yes_Days." and ph_userid=".$v['user_id'].";";
                $our_user_sdt_total=Db::name('profit_24hour')->query($str_temp);
                $our_user_onehour_avg=bcdiv($our_user_sdt_total[0]['total_sdt'],24,15);       //计算每个人每小时sdt的平均值

                $our_user_proportion=bcdiv($our_user_onehour_avg,$our_true_sdt,15);   //每个人的分红比例(每个人每小时的平均值除以总额)
                $our_user_ture_btc=bcmul($our_btc,$our_user_proportion,15);  //每个人应该分的btc数量

                $data=[
                    'pa_userid'=>$v['user_id'],
                    'pa_type'=>1,
                    'pa_btcAmount'=>$our_user_ture_btc,
                    'pa_createTime'=>date('Y-m-d H:i:s'),
                ];
                Db::table('profit_allocation_pre')->insert($data);

            }


            Db::commit();


            //销毁变量释放内存
            unset($true_account);
            unset($our_account);

            $test_sql1="select sum(pa_btcAmount) as total_user from profit_allocation_pre where pa_type=1  and date_format(pa_createTime,'%Y-%m-%d')='".date('Y-m-d')."' and  pa_userid in (select distinct user_id from wallet_group_purse where group_id=2);";
            $test_sql3="select sum(pa_btcAmount) as total_wankejia from profit_allocation_pre where pa_type=1  and date_format(pa_createTime,'%Y-%m-%d')='".date('Y-m-d')."' and  pa_userid in (select distinct user_id from wallet_group_purse where group_id=7);";
            $re1=Db::table('profit_allocation_pre')->query($test_sql1);
            $re3=Db::table('profit_allocation_pre')->query($test_sql3);

            $info['total_user']=$re1[0]['total_user'];
            $info['total_wankejia']=$re3[0]['total_wankejia'];
            $info['btc_charge']=$btc_charge;

            //true allocation data
            Db::table('profit_btc')->insert([
                'true_btc'=>$true_btc,
                'btc_base'=>$btc_base,
                'btc_charge'=>$btc_charge,
                'total_user'=>$re1[0]['total_user'],
                'total_wankejia'=>$re3[0]['total_wankejia'],
                'creat_time'=>date('Y-m-d H:i:s'),
            ]);
            $total_btc=bcadd($re3[0]['total_wankejia'],$re1[0]['total_user'],15);

            $email[]='abdi1006@foxmail.com';
            $email[]='1477563131@qq.com';
            foreach($email as $v){
                $title = '预分红成功';                //主题
                $body ='用户： '.$re1[0]['total_user'].'<br/> 玩客家: '.$re3[0]['total_wankejia'].'<br/> 总共：'.$total_btc.' <br/> 手续费 ：'.$btc_charge.'<br/> 如果需停止分红点击链接：http://mobileapi.starbridgechain.com/wallet/allocation/stopBtcAnalyze';
                Email::sendEmail2($title,[$v],$body);
            }


            return jorx(['code' => 200,'msg' => 'SDT和BTC分红成功','data'=>$info]);

        } catch (\Exception $e){
            Db::rollback();
            return jorx(['code' => 402,'msg' => '分红失败','error' => $e->getMessage()]);
        }


    }


    /*
     * 禁止分红
     * */
    public function stopBtcAnalyze()
    {
        $info=Db::table('profit_syssetting')->where(['pss_id'=>2])->setField('btc_type',2);
        if($info==1){
            return jorx(['code' => 200,'msg' => '分红停止成功']);
        }
    }



    /**
     * sdt分红
     */
    public function dividend($ip)
    {
        $time = date("Y-m-d",strtotime("-1 day"));
        $price = Db::name('wkj_trade_logsbc_btc')
            ->whereBetween('addtime' , [strtotime($time." 00:00:00") , strtotime($time . " 23:59:59")])
            ->order('price desc')
            ->find();
        if($price==null){
            $this->rankings();  //调用分红记录插入
            $title = 'SDT没有交易记录';                //主题
            $to = '1477563131@qq.com';           //收件人
            $body = $ip.'在'.date('Y-m-d H:i:s').':  SDT没有分红';
            Email::sendEmail2($title,[$to],$body);
            return jorx(['code' => 200,'msg' => '昨日没有sdt交易记录']);
        }



        $buys = Db::name('wkj_trade_logsbc_btc')
            ->alias('wfs')
            ->field('wfs.*,wu.fd_id')
            ->join('wallet_purse wp' , 'wfs.userid = wp.fd_id')
            ->join('wallet_user wu' , 'wp.fd_userId = wu.fd_id')
            ->whereBetween('wfs.addtime' , [strtotime($time." 00:00:00") , strtotime($time . " 23:59:59")])
            ->where(['wfs.price'=>$price['price']])
            ->select();



        $arr = [];
        $index = [];

        //最高价格购入的用用户
        foreach ($buys as $key => $value)
        {

            if(!in_array($value['fd_id'] , $index)){    //新用户加入数组
                $index[] = $value['fd_id'];
                $arr[] = [
                    'fd_id' => $value['fd_id'],
                    'num' => $value['num'],      //个人成交量
                ];
            } else {              //老用户交易数量叠加
                for ($i = 0 ; $i < count($arr) ; $i ++) {
                    if($arr[$i]['fd_id'] == $value['fd_id']) {
                        $arr[$i]['num'] = bcadd($arr[$i]['num'],$value['num'],8);
                    }
                }
            }
        }

        //删除交易数量不够1000的用户,统计交易总额
        $total_fh=0;
        foreach($arr as $k=>$v){
            if($v['num']<1000){
                unset($arr[$k]);
                continue;
            }
            $total_fh=bcadd($v['num'],$total_fh,8);
        }


        $setting = Db::name('profit_syssetting')->find();

        //这里是对超级钱包，账户总额的扣钱操作
        $sbc = Db::name('wallet_user')->where('fd_id' , 734)->value('fd_sbcNums');
        $sbc = bcsub($sbc , $setting['pss_dividendBase'] , 15);
        Db::name('wallet_user')->where('fd_id' , 734)->setField('fd_sbcNums' , $sbc);

        //减去9号钱包，sbc账户里面的,分红3w sdt数量
        $coin_sbc = Db::name('wkj_user_coinsbc')->where('userid' , 3672)->value('sbc');
        $coin_sbc = bcsub($coin_sbc , $setting['pss_dividendBase'] , 15);
        Db::name('wkj_user_coinsbc')->where('userid' , 3672)->setField('sbc' , $coin_sbc);



        foreach ($arr as $key => $value)
        {

            //个人分红应得sdt=(个人交易额/总交易额)*当天分红3w  sdt
            $fh = bcmul(bcdiv($value['num'] , $total_fh , 15) , $setting['pss_dividendBase']);

            //找到9号钱包的钱包地址
            $be_adr = Db::name('wallet_purse')->where('fd_id' , 3672)->value('fd_walletAdr');
            $wallet = Db::name('wkj_user_coinsbc')->where('userid' , 3672)->find();


            //这里是对参与用户总账号分红数量的增加操作
            $sbc1 = Db::name('wallet_user')->where('fd_id' , $value['fd_id'])->value('fd_sbcNums');
            $sbc1 = bcadd($sbc1 , $fh , 15);
            Db::name('wallet_user')->where('fd_id' , $value['fd_id'])->setField('fd_sbcNums' , $sbc1);

            //对用户的sbc账户进行数据操作
            $puser = Db::name('wallet_purse')->where('fd_userId' , $value['fd_id'])->find();
            $coin_sbc1 = Db::name('wkj_user_coinsbc')->where('userid' , $puser['fd_id'])->value('sbc');
            $coin_sbc1 = bcadd($coin_sbc1 , $fh , 15);
            Db::name('wkj_user_coinsbc')->where('userid' , $puser['fd_id'])->setField('sbc' , $coin_sbc1);
            Db::name('profit_allocation')->insert([
                'pa_userid' => $value['fd_id'],
                'pa_type' => 2,
                'pa_btcAmount' => $fh,
                'pa_createTime' => date('Y-m-d H:i:s')
            ]);
            Db::name('wallet_transfer_log')->insert([
                'order_no' => createOrderNo(),
                'fd_begin_walletAdr' => $be_adr,
                'fd_end_walletAdr' => $puser['fd_walletAdr'],
                'fd_user_id' => 734,
                'fd_col_user_id' => $value['fd_id'],
                'fd_transfer_sbc' => $fh,
                'fd_before_sbc' => $wallet['sbc'],
                'fd_after_sbc' => $coin_sbc,
                'created_time' => date('Y-m-d H:i:s'),
            ]);
	     //插入排行表
            $total_sdt=Db::table('profit_rankings')->where(['pa_userid'=>$value['fd_id'],'pa_type'=>2])->value('pa_total');
            if($total_sdt){
                Db::table('profit_rankings')->where(['pa_userid'=>$value['fd_id'],'pa_type'=>2])->setField('pa_total',bcadd($total_sdt,$fh,8));
            }else{
                Db::table('profit_rankings')->insert([
                    'pa_userid'=>$value['fd_id'],
                    'pa_total'=>$fh,
                    'pa_type'=>2,
                    'pa_createTime'=>date('Y-m-d H:i:s'),
                ]);
            }
        }
//        $this->rankings();  //调用分红记录插入
        //分红成功后发送邮件
        $title = 'SDT分红成功';                //主题
        $to = '1477563131@qq.com';           //收件人
        $body = $ip.'在'.date('Y-m-d H:i:s').':  SDT分红成功';
        Email::sendEmail2($title,[$to],$body);
    }



    /*
     * 获取uuid虚增sdt
     * */
    public function addSdt(){
        ini_set('memory_limit','3072M');
        set_time_limit(0);
        $url='http://Coin.starbridgechain.com:8080/api/WalletService/BatchextractSDTValue?AppKey=RouterApp';
        $re=json_decode(Curl::get($url));


        $purse=Db::table('rt_wallet')->select();
        $arr=[];
        foreach($purse as $item){
            $arr[$item['fd_routerUUid']]=$item['fd_walltId'];
        }
        foreach($re as $k=>$v){
            $ture=array_key_exists(strtolower($v->UUID), $arr);
            if($ture == true){
                $sdt_num=Db::table('wkj_user_coinsbc')->where(['userid'=>$arr[strtolower($v->UUID)]])->value('sbc');
                $sbc=bcadd($sdt_num,$v->Amount,15);

                //更新账户总余额
                $account_num=Db::table('wallet_purse')->field('wallet_user.fd_id,wallet_user.fd_sbcNums')->join('wallet_user','wallet_user.fd_id=wallet_purse.fd_userId','left')->where(['wallet_purse.fd_id'=>$arr[strtolower($v->UUID)]])->find();

                $add_sbc=bcadd($account_num['fd_sbcNums'],$v->Amount,15);
                Db::table('wallet_user')->where(['fd_id'=>$account_num['fd_id']])->setField('fd_sbcNums',$add_sbc);


                //更新sbc余额
                Db::table('wkj_user_coinsbc')->where(['userid'=>$arr[strtolower($v->UUID)]])->setField('sbc',$sbc);
            }
        }
        //销毁变量释放内存
        unset($arr);
        unset($re);
    }



    /*
     * 分红排行榜
     * */
    public  function rankings(){
        ini_set('memory_limit','3072M');
        $sql="select distinct  user_id from wallet_group_purse where group_id=2";
        $info=Db::table('wallet_group_purse ')->query($sql);
        $btc=[];
        foreach($info as  $k=>$v){

            $re=Db::table('profit_allocation ')->where(['pa_userid'=>$v['user_id'],'pa_type'=>1])->sum('pa_btcAmount');
            $temp['pa_userid']=$v['user_id'];
            if($v['user_id']==10){
                continue;
            }
            $temp['pa_total']=$re;
            $temp['pa_type']=1;
            $temp['pa_createTime']=date('Y-m-d H:i:s');
            $btc[]=$temp;

        }
        $sdt=[];
        foreach($info as  $k=>$v){
            $re=Db::table('profit_allocation ')->where(['pa_userid'=>$v['user_id'],'pa_type'=>2])->sum('pa_btcAmount');
            $temp['pa_userid']=$v['user_id'];
            if($v['user_id']==10){
                continue;
            }
            $temp['pa_total']=$re;
            $temp['pa_type']=2;
            $temp['pa_createTime']=date('Y-m-d H:i:s');
            $sdt[]=$temp;

        }
        Db::name('profit_rankings ')->insertAll($btc);
        Db::name('profit_rankings ')->insertAll($sdt);
        unset($btc);
        unset($sdt);


    }


    /*
     * 每天获取23:59分获取今日待分配(today_accumulated)的数量,修改昨日显示yesterday_accumulated字段
     * */
    public function editTodayAcc(){
        $id=Db::table('profit_todayBase')->field('ptb_id,today_accumulated')->order('ptb_createTime desc')->limit(1)->find();
        $re=Db::table('profit_todayBase')->where(['ptb_id'=>$id['ptb_id']])->update([
            'yesterday_accumulated'=>$id['today_accumulated'],
        ]);
    }


    /*
     * 分红排行显示,累计分红
     * */

    public function  displayRankings(Request $request){
        $limit = $request->param('limit');
        $page = $request->param('page');
        $type = $request->param('type');
        $arr=['1','2'];
        if(!in_array($type,$arr)){
            return jorx(['code' => 402,'msg' => 'type,必须为1或者2!','e_msg'=>'The type has to be 1 or 2']);
        }
        if($limit ==''|| (int)$limit<=0)
            $limit=10;
        if($page==''|| (int)$page<=0)
            $page=1;

        $info = Db::name('profit_rankings')
            ->field('a.*,user.fd_userName as name')
            ->alias('a')
            ->join('wallet_user user','a.pa_userid=user.fd_id ','LEFT')
            ->where(['a.pa_type'=>$type])
            ->order('pa_total','desc')
            ->limit(((int)$page-1)*10,(int)$limit)
            ->select();
        if(count($info)==0){
            $info = Db::name('profit_rankings')
                ->field('a.*,user.fd_userName as name')
                ->alias('a')
                ->join('wallet_user user','a.pa_userid=user.fd_id ','LEFT')
                ->where(['a.pa_type'=>$type])
                ->order('pa_total','desc')
                ->limit(((int)$page-1)*10,(int)$limit)
                ->select();
        }

        $order=($page-1)*$limit+1;
        foreach($info as $k=>$v){
            $info[$k]['order']=$order;
            $order++;
        }
        return jorx(['code' => 200,'msg' => '获取成功!','data'=>$info]);
    }



    /*获取昨日SDT和BTC排行*/
    public function  yesRanking(){

        $today = date("Y-m-d");
        $num=Db::table('profit_allocation')->whereBetween('pa_createTime' , [$today." 00:00:00" , $today . " 23:59:59"])->count();
        if($num==0){
            $time = date("Y-m-d",strtotime("-1 day"));
        }else{
            $time=$today;
        }
        $where['pa_type']=1;
        $where['pa_userid']=['not in','9,10'];
        $info['btc']=Db::table('profit_allocation')
            ->field('a.pa_btcAmount,user.fd_userName as name')
            ->alias('a')
            ->join('wallet_user user','a.pa_userid=user.fd_id ','LEFT')
            ->whereBetween('pa_createTime' , [$time." 00:00:00" , $time . " 23:59:59"])
            ->where($where)
            ->order('pa_btcAmount desc')
            ->limit(30)
            ->select();
        $info['sdt']=Db::table('profit_allocation')
            ->field('a.pa_btcAmount,user.fd_userName as name')
            ->alias('a')
            ->join('wallet_user user','a.pa_userid=user.fd_id ','LEFT')
            ->whereBetween('pa_createTime' , [$time." 00:00:00" , $time . " 23:59:59"])
            ->where(['pa_type'=>2])
            ->order('pa_btcAmount desc')
            ->limit(30)
            ->select();
        return jorx(['code' => 200,'msg' =>'成功','data'=>$info]);

    }

    /*最新成交显示排行*/
    public function latestDeal(){
        $time = date("Y-m-d");
        $re = Db::name('wkj_trade_logsbc_btc')
            ->field(['price'])
            ->whereBetween('addtime' , [strtotime($time." 00:00:00") , strtotime($time . " 23:59:59")])
            ->order('price desc')
            ->find();

        $all=Db::name('wkj_trade_logsbc_btc')
            ->alias('wfs')
            ->field('wfs.id,wfs.num,wfs.price,wu.fd_userName')
            ->join('wallet_purse wp' , 'wfs.userid = wp.fd_id')
            ->join('wallet_user wu' , 'wp.fd_userId = wu.fd_id')
            ->whereBetween('wfs.addtime' , [strtotime($time." 00:00:00") , strtotime($time . " 23:59:59")])
            ->where(['wfs.price'=>$re['price']])
            ->select();

        $arr=[];
        $index=[];
        $total=0;
        foreach($all as $item){
            if(!in_array($item['fd_userName'], $index)){    //新用户加入数组
                $index[]=$item['fd_userName'];
                $arr[] = [
                    'fd_userName' => $item['fd_userName'],
                    'price' =>$item['price'],      //个人成交量
                    'num' =>$item['num'],      //个人成交量
                ];
                $total+=$item['num'];
            } else {              //老用户交易数量叠加
                for ($i = 0 ; $i < count($arr) ; $i ++) {
                    if($arr[$i]['fd_userName'] ==  $item['fd_userName']) {
                        $arr[$i]['num'] = bcadd($arr[$i]['num'],$item['num'],8);
                    }
                }
                $total+=$item['num'];
            }
        }

        $sdtbase=Db::name('profit_syssetting')->value('pss_dividendBase');
        foreach($arr as  $k=>$v){
            if($v['num']<1000){
                $total=$total-$v['num'];
            }
        }
        $info=[];
        foreach($arr as  $k=>$v){
            if($v['num']<1000) continue;

            $info[]=[
                'fd_userName'=>$v['fd_userName'],
                'price'=>$v['price'],
                'num'=>$v['num'],
                'income'=>bcdiv($v['num'],$total,8)*$sdtbase,
                ];

        }
        return jorx(['code' => 200,'msg' =>'成功','data'=>$info]);

    }

    /*提前两天给锁仓到期的账户发提醒邮件*/
    public function sendExpireLock(){
        $time = date("Y-m-d",strtotime("+2 day"));
        $re = Db::table('wallet_sdt_lock')
            ->field('user_id')
            ->whereBetween('lock_time' , [$time." 00:00:00" , $time . " 23:59:59"])
            ->select();
        foreach($re as $item){
            $email=Db::table('wallet_user')->where(['fd_id'=>$item['user_id']])->value('fd_email');
            $title = '服务－星桥链SDT锁仓到期提醒';                //主题
            $to = $email;           //收件人
            $body = "[starbridgechain] ".$email."，您好,
                    您的<锁仓SDT>将于两日后到期。若单笔需继续锁仓，
                    请登录'星桥链官方http://starbridgechain.io－个人－我的资产－点击锁仓'即可。
                    温馨提醒：因锁仓规则调整，单笔已锁仓部分需达到3000 SDT，方可继续锁仓。
                    若单笔已锁仓部分不足3000SDT，且选择了继续锁仓，到期后统一将自动解仓。";
            Email::sendEmail2($title,[$to],$body);
        }

        return jorx(['code' => 200,'msg' =>'成功']);
    }







    /**
     * 分红记录
     * @param Request $request
     * @return \think\response\Json
     */
    public function getAllocationList(Request $request)
    {
        $user = $request->user;
        $pageNumber = $request->param('pageNumber' , 1);
        $pageSize = $request->param('pageSize' , 10);
        $limit = ($pageNumber - 1) * $pageSize . ',' . $pageSize;
        $where = [];
        if($request->param('type') == 'btc')
        {
            $where['pa_type'] = 1;
        }else if($request->param('type') == 'sdt'){
            $where['pa_type'] = 2;
        }
        $info = Db::name('profit_allocation')
            ->where($where)
            ->where('pa_userid' , $user['fd_id'])
            ->limit($limit)
            ->order('pa_createTime desc')
            ->select();
        $count = Db::name('profit_allocation')
            ->where($where)
            ->where('pa_userid' , $user['fd_id'])
            ->count();
        $btc = Db::name('profit_allocation')
            ->where('pa_userid' , $user['fd_id'])
            ->where('pa_type' , 1)
            ->sum('pa_btcAmount');
        $sdt = Db::name('profit_allocation')
            ->where('pa_userid' , $user['fd_id'])
            ->where('pa_type' , 2)
            ->sum('pa_btcAmount');
        $result['msg'] = '操作成功';
        $result['code'] = 200;
        $result['data'] = $info;
        $result['count'] = $count;
        $result['btc'] = $btc;
        $result['sdt'] = $sdt;
        return json($result);
    }


    /*
     *页面计算
     * */
    public function cal(){
        set_time_limit(0);

        //昨日产出数量
        $yesToday= date("Y-m-d H:i:s",strtotime("-1 day"));
        $where['SSYS_StartTime']=['elt',$yesToday];
        $where['SSYS_EndTime']=['egt' ,$yesToday];
        $num=Db::table('Profit_sdtyearSetting')->where($where)->value("SSYS_SDTAmount");//获取当日的sdt发放量
        $info['yesProduce']=$num;


        //查询昨天的数据页面数据
        $data=Db::table('profit_todayBase')->order('ptb_createTime desc')->limit(1)->select();
        //平台总流通量（可参与分红）
        $info['total_sdt']=$data[0]['total_sdt'];

        //SDT二级市场流通量(除锁仓平台总流通量)
        $str_sql="select sum(sbc)  as total from wkj_user_coinsbc where userid=3670;";
        $suo=Db::table('wkj_user_coinsbc')->query($str_sql);
        $info['two_total_circulate']=bcsub($info['total_sdt'],$suo[0]['total'],15);

        $setting=Db::table('profit_syssetting')->find();
        //今日待分配收入累积折合（待分配折合数量）
        $time=(int)date("H")+1;            //当前请求时间
        $yes_today_profit=bcdiv($data[0]['yesterday_accumulated'],24,15);       //昨日分红总额/24
        $temp_num=mt_rand(1,9);     //今日折合上下浮动
        if($temp_num%2==0){
            $stte_num=$setting['floating_down'];
        }else{
            $stte_num=$setting['floating_up'];
        }
        //今日待分配,每个小时变化一次
        $info['today_accumulated']=$yes_today_profit*$time*$stte_num;
        //锁仓系数,用于计算收益率
        $coefficient=Db::table('profit_syssetting')->value('coefficient');

        //非锁仓,持有SDT每万份收益
        $info['yesterday_million_btc']=bcdiv(10000,$data[0]['all_true_sdt'],15)*$data[0]['yesterday_true_accumulated'];

        //锁仓,持有SDT每万份收益
        $info['lock_yesterday_million_btc']=$info['yesterday_million_btc']*$coefficient;

        //最新币价
        $url='http://webmarket.starbridgechain.com/Ajax/getJsonTops?market=sbc_btc';//当前每SDT可兑换BTC
        $new_price=json_decode(Curl::get($url));
        $sdt_price=$new_price->info->new_price;

        //非锁仓,动态收益率
        $info['yesterday_static_sdt']=bcdiv(bcmul($data[0]['yesterday_true_accumulated'],bcdiv(1,$data[0]['all_true_sdt'],20),20),$sdt_price,5)*1000;
        //锁仓,动态收益率
        $info['lock_yesterday_static_sdt']=bcmul($info['yesterday_static_sdt'],$coefficient,15);



        //矿机设备含有数量（不参与分红）
        $urls='https://apiportal.cmbcrouter.com:8020/api/Management/GetTodayCollect';
        $equipment_nums=json_decode(Curl::post($urls,json_encode([]),['Content-Type: application/json']));
        $info['equipment_num']=$equipment_nums->Body->Data->AllRouter;
        //已释放数量
        $info['all_total_sdt']=bcadd($info['total_sdt'],$info['equipment_num'],15);

        /*修改数据库*/
        $id=Db::table('profit_todayBase')->field('ptb_id')->order('ptb_createTime desc')->limit(1)->find();
        $re=Db::table('profit_todayBase')->where(['ptb_id'=>$id['ptb_id']])->update([
            'yesProduce'=>$info['yesProduce'],
            'today_accumulated'=>$info['today_accumulated'],
            'two_total_circulate'=>$info['two_total_circulate'],
            'all_total_sdt'=>$info['all_total_sdt'],
            'equipment_num'=>$info['equipment_num'],
            'yesterday_million_btc'=>$info['yesterday_million_btc'],
            'yesterday_static_sdt'=>$info['yesterday_static_sdt'],
            'lock_yesterday_static_sdt'=>$info['lock_yesterday_static_sdt'],
            'lock_yesterday_million_btc'=>$info['lock_yesterday_million_btc'],
        ]);
        $info=[];
        //每小时查看是否有解仓数据
        $this->unlockSdt();
    }




    /*
     * 页面显示
     * */
    public function calculation(){
        $data=Db::table('profit_todayBase')->order('ptb_createTime desc')->limit(1)->select();
        return jorx(['code' => 200,'msg' => '页面显示计算!','data'=>$data]);
    }


    /*
     *
     * 给app分配btc转账地址，锁定地址3天内其他用户不可使用
     *
     * */
    public function bindBtcAddr(Request $request){
        $user = $request->user;
        $staleDated= date("Y-m-d H:i:s",strtotime("+3 year"));
        $now=date("Y-m-d H:i:s");
        //已绑定,查询返回
        $where['type'] = ['eq' ,2];
        $where['user_id'] = ['eq',$user['fd_id']];
        $where['stale_date'] = ['gt' ,$now];
        $addr=Db::table('wallet_bind_addr')->field('id,addr')->where($where)->find();

        if($addr!=null){
            return jorx(['code' => 200,'msg' => '已存在!','data'=>$addr]);
        }


        //获取空闲btc地址
        $addr=Db::table('wallet_bind_addr')->field('id,addr')->where(['type'=>1])->order('id','asc')->find();
        if($addr!=null){

            $arr['type']=2;
            $arr['stale_date']=$staleDated;
            $arr['user_id']=$user['fd_id'];

            $re=Db::table('wallet_bind_addr')->where(['addr'=>$addr['addr']])->update($arr);

            return jorx(['code' => 200,'msg' => '空地址绑定成功!','data'=>$addr]);
        }


        //获取已绑定,过期地址
        $wheres['type'] = ['eq' ,2];
        $addr=Db::table('wallet_bind_addr')->field('id,addr')->where($wheres)->order('id','asc')->find();
        if($addr!=null){
            $arr['type']=2;
            $arr['stale_date']=$staleDated;
            $arr['user_id']=$user['fd_id'];

            $re=Db::table('wallet_bind_addr')->where(['addr'=>$addr['addr']])->update($arr);
            return jorx(['code' => 200,'msg' => '预绑定,改绑成功!','data'=>$addr]);
        }

        //获取已使用，过期的地址
        $wheres['type'] = ['eq' ,3];
        $addr=Db::table('wallet_bind_addr')->field('id,addr')->where($wheres)->order('id','asc')->find();
        if($addr!=null){
            $arr['type']=2;
            $arr['stale_date']=$staleDated;
            $arr['user_id']=$user['fd_id'];

            $re=Db::table('wallet_bind_addr')->where(['addr'=>$addr['addr']])->update($arr);
            return jorx(['code' => 200,'msg' => '已使用,改绑成功!','data'=>$addr]);
        }
        return jorx(['code' => 200,'msg' => '没有空闲地址!','data'=>null]);
    }



    /*
     * 锁仓
     * */
    public function lockSdt(Request $request){


        $user = $request->user;
        $num = $request->param('num');
        $type = $request->param('type');
        $arr=['1','2'];
        $lock_num = $request->param('lock_num',3);
        if($lock_num<3||$lock_num==''){
            return jorx(['code' => 402,'msg' => 'lock_num,必须大于3!','e_msg'=>'The lock_num must be greater than or be equal 3!']);
        }
        if(!in_array($type,$arr)){
            return jorx(['code' => 402,'msg' => 'type,必须为1或者2!','e_msg'=>'The type has to be 1 or 2']);
        }
        $setting=Db::table('profit_setting_lock')->order('createTime desc')->limit(1)->select();

        if($num <= 0||$num <$setting[0]['sdtBase']){
            return jorx(['code' => 402,'msg' => '请确认锁仓金额,金额必须大于'.$setting[0]['sdtBase'],'e_msg'=>'Please confirm the amount of lock bin,the amount must be greater than 1000!']);
        }
        //查询账户可用余额
        //$wallet_user = Db::name('wallet_user')->where('fd_id' , $user['fd_id'])->find();
        $sql='select *  from  wkj_user_coinsbc where userid in (select fd_id  from wallet_purse where fd_userId='.$user['fd_id'].');';
        $sdt_account=Db::name('wkj_user_coinsbc')->query($sql);

        $total_sdt=0.00000000000000;
        foreach($sdt_account as $k=>$v){
            $total_sdt+=$v['sbc'];
            $total_sdt+=$v['sbcd'];
        }

        $user_balance = Db::name('wallet_user')->where('fd_id' , $user['fd_id'])->find();

        if($num > $total_sdt) {
            return jorx(['code' => 402,'msg' => '账户可用余额不足!','e_msg'=>'Insufficient available balance !']);
        }


        if($num>$user_balance['fd_sbcNums']){
            return jorx(['code' => 402,'msg' => '锁仓失败，账户总余额不足,请联系管理!','e_msg'=>'Lock up failure !']);
        }
        if((int)$total_sdt!=(int)($user_balance['fd_sbcNums'])){
            return jorx(['code' => 402,'msg' => '锁仓失败，账户余额出现差入,请联系管理!','e_msg'=>'Lock up failure !']);
        }

        Db::startTrans();
        try {
            //更新用户的sdt总余额，
            $subtraction_sdts = bcsub($user_balance['fd_sbcNums'],$num,10);//总余额，减去锁仓的sdt数量


            $add_sdts = bcadd($user_balance['lock_sbcNums'],$num,10);  //加锁仓数量
            Db::name('wallet_user')->where('fd_id' , $user['fd_id'])->update([
                'fd_sbcNums'=>$subtraction_sdts,
                'lock_sbcNums'=>$add_sdts,
            ]);

            $lock_time=date("Y-m-d H:i:s",strtotime("+3 months"));
            //插入锁仓记录表
            Db::name('wallet_sdt_lock')->insert([
                'batch'=>$setting[0]['batch'],
                'user_id'=>$user['fd_id'],
                'lock_nums'=>$num,
                'lock_time'=>$lock_time,
                'type'=> $type,
                'time_num'=>$lock_num,
                'create_time'=>date("Y-m-d H:i:s")

            ]);

            //逐个减去账户里面的sdt数量
            foreach($sdt_account as $k=>$v){
                if($v['sbc']>$num){
                    $sdt_num=bcsub($v['sbc'],$num,15);
                    Db::table('wkj_user_coinsbc')->where(['id'=>$v['id']])->setField('sbc',$sdt_num);
                    break;
                }else if($v['sbc']==$num){
                    Db::table('wkj_user_coinsbc')->where(['id'=>$v['id']])->setField('sbc',0);
                    break;
                }else{
                    Db::table('wkj_user_coinsbc')->where(['id'=>$v['id']])->setField('sbc',0);
                    $num=bcsub($num,$v['sbc'],15);
                    if($num==0) break;
                }
            }
            Db::commit();
            return jorx(['code' => 200,'msg' => '锁仓成功!','e_msg'=>'Lock up success !','data'=>['locak_time'=>$lock_time,'lock_times'=>$lock_num]]);

        } catch (\Exception $e){
            Db::rollback();
            return jorx(['code' => 402,'msg' => '锁仓失败，请稍后再试!','e_msg'=>'Lock up failure !']);
        }


    }



    /*
     * 查询锁仓列表
     * */
    public function lockList(Request $request){
        $user = $request->user;
        $locks=Db::name('wallet_sdt_lock')->where(['user_id'=>$user['fd_id']])->select();
        return jorx(['code' => 200,'msg' => '成功','e_msg'=>'success!','data'=>$locks]);
    }

    /*
     * 锁仓续期修改
     * */
    public function editLockTime(Request $request){
        $type = $request->param('type');
        $id = $request->param('id');
        $arr=['1','2'];
        if(!in_array($type,$arr)){
            return jorx(['code' => 402,'msg' => 'type,必须为1或者2!','e_msg'=>'The type has to be 1 or 2']);
        }
        if($type==1){   //锁仓续期，到期的时候锁仓额不满足最低锁仓余额，不能续期锁仓
            $lock_nums=Db::name('wallet_sdt_lock')->where(['id'=>$id,'status'=>1])->value('lock_nums');
            $base=Db::name('profit_setting_lock')->order('createTime desc')->limit(1)->select();
            if($lock_nums<$base[0]['sdtBase']){
                return jorx(['code' => 201,'msg' => '余额不足','e_msg'=>'failed!']);
            }
        }


        $re=Db::name('wallet_sdt_lock')->where(['id'=>$id,'status'=>1])->setField('type',$type);
        if($re){
            return jorx(['code' => 200,'msg' => '成功','e_msg'=>'success!']);
        }else{
            return jorx(['code' => 201,'msg' => '失败','e_msg'=>'failed!']);
        }



    }

    /*
     * 解仓
     * */
    public function unlockSdt(){
        $time=date('Y-m-d H:i:s');
        $where['lock_time']=['elt' ,$time];
        $where['status']=['eq' ,1];
        $setting=Db::table('profit_setting_lock')->order('createTime desc')->limit(1)->select();
        $locks=Db::name('wallet_sdt_lock')->where($where)->select();
        foreach($locks as $k=>$v){
            Db::startTrans();
            try {
                if($v['type']==1&&$v['lock_nums']>=$setting[0]['sdtBase']){       //到期继续锁仓
                    $lock_time=date("Y-m-d H:i:s",strtotime("+".$v['time_num']." months"));
                    Db::name('wallet_sdt_lock')->where(['id'=>$v['id'],'status'=>1])->setField('lock_time',$lock_time);

                }else{          //到期解仓
                    //扣去相应的锁仓数量
                    $user_balance = Db::name('wallet_user')->where('fd_id' , $v['user_id'])->find();

                    if($v['lock_nums']>$user_balance['lock_sbcNums']){
                        return jorx(['code' => 402,'msg' => '解仓失败,解仓数量大于锁仓数量,请联系管理!','e_msg'=>'Lock up failure !']);
                    }
                    $unlock_num=bcsub($user_balance['lock_sbcNums'],$v['lock_nums'],15);
                    Db::name('wallet_user')->where('fd_id' , $v['user_id'])->setField('lock_sbcNums',$unlock_num);

                    //想相应的账户中，补充相应的sdt数量
                    $soinsdt = Db::name('wallet_purse')
                        ->field(['wallet_purse.fd_id','wkj_user_coinsbc.*'])
                        ->join('wkj_user_coinsbc','wallet_purse.fd_id=wkj_user_coinsbc.userid','left')
                        ->where(['fd_userId'=>$v['user_id']])
                        ->find();
                    $add_num=bcadd($v['lock_nums'],$soinsdt['sbc'],15);
                    Db::name('wkj_user_coinsbc')->where(['id'=>$soinsdt['id']])->setField('sbc',$add_num);

                    //账户加入余额
                    $fd_sbcNums=bcadd($v['lock_nums'],$user_balance['fd_sbcNums'],15);
                    Db::name('wallet_user')->where('fd_id' , $v['user_id'])->setField('fd_sbcNums',$fd_sbcNums);
                    //修改记录状态
                    Db::name('wallet_sdt_lock')->where(['id'=>$v['id']])->setField('status',2);

                    /*解仓后发送邮件*/
                    $email=Db::table('wallet_user')->where(['fd_id'=>$v['user_id']])->value('fd_email');
                    $title = '服务－星桥链SDT到期解仓提醒';                //主题
                    $to = $email;           //收件人
                    $body = "[starbridgechain] ".$email.",
                            您好，您的SDT锁仓资产已于今日解仓，系统会自动将该笔锁仓SDT发放到该账户的可用余额，请注意查收。";
                    Email::sendEmail2($title,[$to],$body);

                }

                Db::commit();

            } catch (\Exception $e){
                Db::rollback();
            }
        }


    }


    /*临时处理余额不正确*/
    public function updateSdt(Request $request)
    {
        $username = $request->param('username');
        $fd_id=Db::name('wallet_user')->where(['fd_userName'=>$username])->value('fd_id');

        $arr_id=Db::name('wallet_purse')->field(['fd_id'])->where(['fd_userId'=>$fd_id])->select();
        $arr=[];
        foreach ($arr_id as $k=>$v) {
            $arr[]=$v["fd_id"];
        }
        $sbc=Db::name('wkj_user_coinsbc')->where(['userid'=>['in',$arr]])->sum('sbc');
        $sbcd=Db::name('wkj_user_coinsbc')->where(['userid'=>['in',$arr]])->sum('sbcd');
        $fd_id=Db::name('wallet_user')->where(['fd_id'=>$fd_id])->setField('fd_sbcNums',bcadd($sbc,$sbcd,15));
        if($fd_id){
            return jorx(['code' => 200,'msg' => '成功']);
        }else{
            return jorx(['code' => 200,'msg' => '余额已经同步，不用再次更同步']);
        }



    }

    public function selectLock()
    {

        $sql='select sum(lock_sbcNums) as lock_sdt_num  from wallet_user where lock_sbcNums!=0 and fd_id!=9 and fd_id!=10;';
        $lock_sdt=Db::name('wallet_user')->query($sql);
        $info['锁仓SDT数量']=$lock_sdt[0]['lock_sdt_num'];

        $str1="select sum(c.btc+c.btcd) as user_btc from wkj_user_coinbtc c right join (select distinct user_id from wallet_group_purse where group_id=2) a on c.userid=a.user_id;";
        $re1=Db::table('wkj_user_coinbtc')->query($str1);

        /*$str2="select sum(c.btc+c.btcd)as m_btc from wkj_user_coinbtc c right join (select distinct user_id from wallet_group_purse where group_id=6) a on c.userid=a.user_id;";
        $re2=Db::table('wkj_user_coinbtc')->query($str2);*/
        $str3="select sum(c.btc+c.btcd) as w_btc from wkj_user_coinbtc c right join (select distinct user_id from wallet_group_purse where group_id=7) a on c.userid=a.user_id;";
        $re3=Db::table('wkj_user_coinbtc')->query($str3);

        //$tottal_btc=bcadd(bcadd($re1[0]['user_btc'],$re2[0]['m_btc'],10),$re3[0]['w_btc'],10);
        $tottal_btc=bcadd($re1[0]['user_btc'],$re3[0]['w_btc'],10);

        $info['用户总BTC']=$tottal_btc;
        $re=Db::table('btc_total')->order('time desc')->limit(1)->find();

        $email[]='abdi1006@foxmail.com';
        $email[]='1477563131@qq.com';
        $email[]='danyan@tjgehua.com';
        if($tottal_btc>$re["btc"]){
            foreach($email as $v){
                $body = date('Y-m-d H:i:s').'--btc数量大于上次数量,上次数量为： '.$re["btc"].' 此次数量为： '.$tottal_btc;
                Email::sendEmail2('btc数量增加',[$v],$body);
            }
        }

        Db::table('btc_total')->insert([
           'btc'=>$tottal_btc,
           'time'=>date("Y-m-d H:i:s")
            ]
        );





        return jorx(['code' => 200,'msg' => '成功','data'=>$info]);

    }

    /*定时删除快照*/
    public function delHour(){
        set_time_limit(0);
        ini_set('memory_limit','3072M');
        $yesToday= date("Y-m-d",strtotime("-7 day"));

        /*$sql="select ph_userid,ph_sdt,ph_createTime,ph_hour,ph_days from profit_24hour where  date_format(ph_createTime,'%Y-%m-%d')<'".$yesToday."' ;";
        $re=Db::table('profit_24hour')->query($sql);
        Db::table('profit_24hourHistory')->insertAll($re);*/

        $delete_sql="delete  from profit_24hour where  date_format(ph_createTime,'%Y-%m-%d')<'".$yesToday."' ;";
        Db::table('profit_24hour')->query($delete_sql);
        return jorx(['code' => 200,'msg' => '快照数据删除成功']);
    }



}




