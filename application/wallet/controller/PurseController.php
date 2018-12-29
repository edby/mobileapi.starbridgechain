<?php


namespace app\wallet\controller;


use app\wallet\model\Purse;
use think\Db;
use think\Request;

class PurseController extends BaseController
{

    /**
     * 获取钱包地址列表
     * @param Request $request
     * @param Purse $purse
     * @return \think\response\Json
     */
    public function getPurse(Request $request,Purse $purse)
    {
        $user  = $request->user;
        //将用户的第一个钱包设置成特殊钱包
        $purse_type=Db::table('wallet_purse')
            ->alias('purse')
            ->field('sbc.*,purse.fd_id,purse.fd_userId')
            ->join('wkj_user_coinsbc sbc','sbc.userid=purse.fd_id')
            ->join('wallet_user user','user.fd_id=purse.fd_userId')
            ->where('user.fd_id',$user['fd_id'])
            ->order('sbc.id asc')
            ->find();

        if($purse_type['type']==1){
            Db::table('wkj_user_coinsbc')->where('id',$purse_type['id'])->setField('type',2);
        }


        //返回钱包列表
        $page  = $request->param('page',0) - 0;
        $limit = $request->param('limit',10) - 0;
        $data  = $purse->join('wkj_user_coinsbc','wkj_user_coinsbc.userid = wallet_purse.fd_id','LEFT')
            ->where('wallet_purse.fd_userId',$user['fd_id'])
            ->field([
                    'wallet_purse.fd_id as walletId',
                    'wallet_purse.fd_walletAdr as address',
                    'wkj_user_coinsbc.sbc as sbc',
                    'wkj_user_coinsbc.sbcd as sbcd',
                    'wkj_user_coinsbc.type'
                ]
            );
        if($page !== 0){
            $data = $data->page($page,$limit);
        }
        $data = $data->select();


        if(empty($data)){
            return jorx(['code' => 201,'msg' => '数据为空！','e_msg'=>'data is  empty!','data' => []]);
        }
        foreach ($data as &$item){
            //var_dump($item['sbc']);die;
           // $item['sbc']  = n_f(number_format($item['sbc'] - 0,10,'.',''));
           // $item['sbcd'] = n_f(number_format($item['sbcd'] - 0,10,'.',''));
            $item['sbc']  = $item['sbc'];
            $item['sbcd'] = $item['sbcd'];
        }
        $btc  = Db::table('wkj_user_coinbtc')->where('userid',$user['fd_id'])->value('btc');
        $total_num= $purse->join('wkj_user_coinsbc','wkj_user_coinsbc.userid = wallet_purse.fd_id','LEFT')
            ->where('wallet_purse.fd_userId',$user['fd_id'])
            ->field([
                    'wallet_purse.fd_id as walletId',
                    'wallet_purse.fd_walletAdr as address',
                    'wkj_user_coinsbc.sbc as sbc',
                    'wkj_user_coinsbc.sbcd as sbcd',
                    'wkj_user_coinsbc.type'
                ]
            )->count();

        return jorx(['code' => 200,'msg' => '获取成功！','e_msg'=>'success','total_num'=>$total_num,'data' => $data,'btc' => $btc ? number_format($btc,4,'.','') : '0.0000' ]);
    }

    /**
     * 钱包转账
     * @param Request $request
     * @return \think\response\Json
     */
    public function walletTransfer(Request $request)
    {
        //判断转账功能接口,是否可以使用,1是可以使用,0是不可以使用
//        $file=file_exists("switch.json");
//        if ($file){
//            $json_string = file_get_contents("switch.json");
//            $data = json_decode($json_string,true);
//
//            if($data['switch'][0]==2){
//                return jorx(['code' => 400,'msg' => '转账功能暂时关闭!']);
//            }
//        }else{
//
//            $fp=fopen("switch.json","w+");
//            fclose($fp);
//            $data['switch'][0]=1;         //修改文件值
//            $json_strings = json_encode($data);
//            file_put_contents("switch.json",$json_strings);
//
//        }

        $switch = Db::table('wallet_config')
            ->where('fd_id','=',1)
            ->value('fd_wallet_switch');
        if ($switch) {
            if ($switch == 2) {
                return jorx(['code' => 400,'msg' => '转账功能暂时关闭!']);
            }
        } else {
            return jorx(['code' => 400, 'msg' => '系统错误,稍后再试!']);
        }

        $user = $request->user;
        $begin_walletAdr = $request->param('begin_walletAdr');
        $end_walletAdr = $request->param('end_walletAdr');
        $num = $request->param('num');

        /*
        * 判断是否是 特殊账户提取 btc
        * */
        $user_info = Db::name('wallet_user')->where(['fd_id'=>$user['fd_id']])->find();

        if (empty($user_info)){
            return jorx(['code' => 400,'msg' => '当前登录用户不存在!']);
        }

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
            /**0826应急方案!转出钱包必须 是当前登录人的钱包**/
            $purse = Db::name('wallet_purse')->where('fd_userId' , $user_info['fd_id'])->column('fd_walletAdr');
            if (!in_array($begin_walletAdr,$purse)){
                return jorx(['code' => 400,'msg' => '转账失败!!code:100']);
            }

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

    /*
     * 是否开启转账功能
     * */
    public function switchPurse(Request $request)
    {
        //判断转账功能接口,是否可以使用,true是可以使用,flase是不可以使用
        $switch = $request->param('status');
	//file_put_contents("aa.txt",$switch);

        $file=file_exists("switch.json");
        if ($file){       //如果文件存在
            $json_string = file_get_contents("switch.json");// 从文件中读取数据到PHP变量
            $data = json_decode($json_string,true);// 把JSON字符串转成PHP数组

            $data['switch'][0]=$switch;         //修改文件值,重新写入文件
            $json_strings = json_encode($data);
            file_put_contents("switch.json",$json_strings);

        }else{      //如果文件不存在,创建文件
            $fp=fopen("switch.json","w+");
            fclose($fp);
            $data['switch'][0]=$switch;         //修改文件值getPurse
            $json_strings = json_encode($data);
            file_put_contents("switch.json",$json_strings);
        }
        return jorx(['code' => 200,'msg' => '开启成功!']);
    }

    /*
     * 获取当前转账功能状态
     *
     */
    public  function getStatus(Request $request){
        $json_string = file_get_contents("switch.json");// 从文件中读取数据到PHP变量
        $data = json_decode($json_string,true);// 把JSON字符串转成PHP数组

        return jorx(['code' => 200,'msg' => '获取成功！','data' => $data['switch'][0]]);

    }

}
