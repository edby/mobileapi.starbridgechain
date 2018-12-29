<?php


namespace app\wallet\controller;


use app\wallet\model\Order;
use app\wallet\model\Purse;
use curl\Curl;
use think\Config;
use think\Db;
use think\Request;
use think\helper\Str;
use think\helper\Hash;
use app\wallet\model\User;

class AdminController extends BaseController
{


    /*
     * 是否开启转账功能
     * */
    public function switchPurse(Request $request){

        //判断转账功能接口,是否可以使用,true是可以使用,flase是不可以使用
        $switch = $request->param('status');

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
            $data['switch'][0]=$switch;         //修改文件值
            $json_strings = json_encode($data);
            file_put_contents("switch.json",$json_strings);
        }
        if($switch==1){
            return jorx(['code' => 200,'msg' => '开启成功!']);
        }
        return jorx(['code' => 200,'msg' => '关闭成功!']);



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


    /*
     * BTC excel下载
     * */
    public function out(Request $request,Order $order)
    {

        $field = [
            'wallet_order.fd_id as orderId',
            'wallet_user.fd_userName as username',
            'wallet_purse.fd_walletAdr as wallet',
            'wallet_order.fd_orderNo as orderNo ',
            'wallet_order.fd_type as type ',
            'wallet_order.fd_sbcPrice as sbcPrice ',
            'wallet_order.fd_from as source ',
            'wallet_order.fd_fromNums as sourceNum ',
            'wallet_order.fd_fromAccount as sourceAccount',
            'wallet_order.fd_gone as gone',
            'wallet_order.fd_goneNums as goneNum',
            'wallet_order.fd_goneAccount as goneAccount',
            'wallet_order.fd_fee as fee',
            //  'wallet_order.fd_real_goneNums as realGoneNum',
            'wallet_order.fd_status as status',
            'wallet_order.fd_createDate as createDate',
            'wallet_order.fd_operator as operator',
            'wallet_order.fd_pictureAdr as pictureAdr',
        ];


        $param = $request->param();
        $order = $order
            ->join('wallet_user','wallet_order.fd_userId = wallet_user.fd_id','LEFT')
            ->join('wallet_purse','wallet_order.fd_walletId = wallet_purse.fd_id','LEFT')
            ->field($field);


        //开始时间
        if(isset($param['start_time']) && '' != $param['start_time']){
            $order->where('wallet_order.fd_createDate','>=',$param['start_time'] . ' 00:00:00');
        }

        //结束时间
        if(isset($param['end_time']) && '' != $param['end_time']){

            $order->where('wallet_order.fd_createDate','<=',$param['end_time'] . ' 23:59:59');
        }

        //类型
        $type = isset($param['type']) ? trim($param['type']) : '';


        if($type){
            $order->where('wallet_order.fd_type',$type); //in充值，out提现
        }



        //用户
        if(isset($param['username']) && '' != $param['username']){
            $order->where('wallet_user.fd_userName','like',"%{$param['username']}%");
        }



        //币种
        if(isset($param['coin']) && '' != $param['coin']){
            $order->where("wallet_order.fd_from = '{$param['coin']}' OR wallet_order.fd_gone = '{$param['coin']}'");
        }
        $order_bak =   clone $order;
        $count     = $order_bak->count('wallet_order.fd_id');
        //$limit     = isset($param['limit']) ? $param['limit'] : 10;
        //$page      = isset($param['page']) ? $param['page'] : 1;
        //$data      = $order->page($page,$limit)->order('wallet_order.fd_createDate','desc')->select();

        $data      = $order->order('wallet_order.fd_createDate','desc')->select();
        $map   = [
            'publicWallet' => '公网钱包',
            'wechat'       => '微信',
            'bankcard'     => '银行卡'
        ];



        //修改来源和去向
        foreach ($data as $item) {
            $item->pictureAdr && $item->pictureAdr = $request->root(true) . $item->pictureAdr;

            isset($map[$item->gone])   && $item->gone   = $map[$item->gone];
            isset($map[$item->source]) && $item->source = $map[$item->source];
        }

        //$this->excel();
        return jorx(['code' => 200,'msg' => '获取成功!','count' => $count,'data' => $data]);

    }


    /**
     * SDT钱包交易记录(运维后台)
     * @param Request $request
     * @return \think\response\Json
     */
    public function getSdtData(Request $request)
    {

        $start_time = $request->param('start_time');
        $end_time = $request->param('end_time');
        $from_type = $request->param('type'); //in 转入 out 转出 extract 提取 residue 余额


        $limit = $request->param('limit');
        $page = $request->param('page');
        $user = $request->param('user');

        if($limit ==''|| (int)$limit<=0)
            $limit=10;
        if($page==''|| (int)$page<=0)
            $page=1;

        $where = [];


        if($start_time && $end_time)
            $where['a.created_time'] = ['between' ,[$start_time . ' 00:00:00', $end_time . ' 23:59:59']];


        if ($user){  //搜索必须传userid
            $user_id = Db::name('wallet_user')->where('fd_userName',$user)->value('fd_id');

            if($from_type=='in_all'){         //转入
                $where['a.fd_col_user_id'] = ['eq' , $user_id];
                $where['a.fd_user_id'] = ['neq' , $user_id];
            } else if($from_type=='out') {        //转出
                $where['a.fd_user_id'] = ['eq' , $user_id];
                $where['a.fd_col_user_id'] = ['neq' , $user_id];
            } else if($from_type=='ext'){     //余额整理
                $where['a.fd_user_id'] = ['eq',$user_id];
                $where['a.fd_col_user_id'] = ['eq',$user_id];
            }else{
                $where['a.fd_user_id|a.fd_col_user_id'] = ['eq',$user_id];
            }
        }


        $info = Db::name('wallet_transfer_log')
            ->field('wallet_transfer_log.*,user.fd_userName as from_name,userA.fd_userName as to_name')
            ->alias('a')
            ->join('wallet_user user','a.fd_user_id=user.fd_id ')
            ->join('wallet_user userA','a.fd_col_user_id=userA.fd_id ')
            ->where($where)
            ->order('a.created_time','desc')
            ->limit(((int)$page-1)*10,(int)$limit)
            ->select();


        $count = Db::name('wallet_transfer_log')
            ->field('wallet_transfer_log.*,user.fd_userName as from_name,userA.fd_userName as to_name')
            ->alias('a')
            ->join('wallet_user user','a.fd_user_id=user.fd_id ')
            ->join('wallet_user userA','a.fd_col_user_id=userA.fd_id ')
            ->where($where)
            ->order('a.created_time','desc')
            ->count();

        if(empty($info)){
            return jorx(['code' => 201,'msg' => '数据为空!','data' => []]);
        }else{
            return jorx(['code' => 200,'msg' => '获取成功!','count' => $count,'type'=>$from_type,'data' => $info]);
        }
    }


    /*
     *
     * SDT个人数据
     * */
    public function getData(Request $request)
    {


        $group_id= $request->param('group_id');
        $username= $request->param('username');
        $fd_walletAdr= $request->param('addr');
        $where=[];


        $limit = $request->param('limit');
        $page = $request->param('page');

        if($limit ==''|| (int)$limit<=0)
            $limit=10;
        if($page==''|| (int)$page<=0)
            $page=1;



        if($group_id!=1&&$group_id!='all'){
            $where['wallet_group_purse.group_id']=$group_id;
        }


        if($username!=''){
            $where['wallet_user.fd_userName']=$username;
        }

        if($fd_walletAdr!=''){
            $where['wallet_purse.fd_walletAdr']=$fd_walletAdr;
        }

        if(count($where)!=0){

            $info = Db::name('wkj_user_coinsbc')
                ->field('a.sbc,a.sbcd,wallet_user.fd_id,wallet_user.fd_userName,wallet_purse.fd_walletAdr,wallet_purse.fd_id as purse_id,wallet_group_purse.group_id')
                ->alias('a')
                ->join('wallet_purse  ','a.userid=wallet_purse.fd_id ','left')
                ->join('wallet_user  ','wallet_purse.fd_userId=wallet_user.fd_id ','left')
                ->join('wallet_group_purse ','wallet_purse.fd_id=wallet_group_purse.purse_id ','left')
                ->where($where)
                ->limit(((int)$page-1)*10,(int)$limit)
                ->select();

            $count = Db::name('wkj_user_coinsbc')
                ->field('a.sbc,a.sbcd,wallet_user.fd_id,wallet_user.fd_userName,wallet_purse.fd_walletAdr,wallet_purse.fd_id as purse_id,wallet_group_purse.group_id')
                ->alias('a')
                ->join('wallet_purse  ','a.userid=wallet_purse.fd_id ')
                ->join('wallet_user  ','wallet_purse.fd_userId=wallet_user.fd_id ')
                ->join('wallet_group_purse ','wallet_purse.fd_id=wallet_group_purse.purse_id ')
                ->where($where)
                ->count();

        }else{

            $info = Db::name('wkj_user_coinsbc')
                ->field('a.sbc,a.sbcd,wallet_user.fd_id,wallet_user.fd_userName,wallet_purse.fd_id as purse_id,wallet_purse.fd_walletAdr,wallet_group_purse.group_id')
                ->alias('a')
                ->join('wallet_purse  ','a.userid=wallet_purse.fd_id ')
                ->join('wallet_user  ','wallet_purse.fd_userId=wallet_user.fd_id ')
                ->join('wallet_group_purse ','wallet_purse.fd_id=wallet_group_purse.purse_id ')
                ->limit(((int)$page-1)*10,(int)$limit)
                ->select();
            $count = Db::name('wkj_user_coinsbc')
                ->field('a.sbc,a.sbcd,wallet_user.fd_id,wallet_user.fd_userName,wallet_purse.fd_id as purse_id,wallet_purse.fd_walletAdr,wallet_group_purse.group_id')
                ->alias('a')
                ->join('wallet_purse  ','a.userid=wallet_purse.fd_id ')
                ->join('wallet_user  ','wallet_purse.fd_userId=wallet_user.fd_id ')
                ->join('wallet_group_purse ','wallet_purse.fd_id=wallet_group_purse.purse_id ')
                ->count();
        }

        if(empty($info)){
            return jorx(['code' => 201,'msg' => '数据为空!','data' => $info]);
        }else{
            return jorx(['code' => 200,'msg' => '获取成功!','count' => $count,'data' => $info]);
        }

    }


    /*
     * sdt excel下载
     * */
    public function getDataOut(Request $request)
    {


        $group_id= $request->param('group_id');
        $username= $request->param('username');
        $fd_walletAdr= $request->param('addr');
        $where=[];







        if($group_id!=1&&$group_id!='all'){
            $where['wallet_group_purse.group_id']=$group_id;
        }


        if($username!=''){
            $where['wallet_user.fd_userName']=$username;
        }

        if($fd_walletAdr!=''){
            $where['wallet_purse.fd_walletAdr']=$fd_walletAdr;
        }

        if(count($where)!=0){

            $info = Db::name('wkj_user_coinsbc')
                ->field('a.sbc,a.sbcd,wallet_user.fd_id,wallet_user.fd_userName,wallet_purse.fd_walletAdr,wallet_purse.fd_id as purse_id,wallet_group_purse.group_id')
                ->alias('a')
                ->join('wallet_purse  ','a.userid=wallet_purse.fd_id ')
                ->join('wallet_user  ','wallet_purse.fd_userId=wallet_user.fd_id ')
                ->join('wallet_group_purse ','wallet_purse.fd_id=wallet_group_purse.purse_id ')
                ->where($where)
                ->select();

        }else{

            $info = Db::name('wkj_user_coinsbc')
                ->field('a.sbc,a.sbcd,wallet_user.fd_id,wallet_user.fd_userName,wallet_purse.fd_id as purse_id,wallet_purse.fd_walletAdr,wallet_group_purse.group_id')
                ->alias('a')
                ->join('wallet_purse  ','a.userid=wallet_purse.fd_id ')
                ->join('wallet_user  ','wallet_purse.fd_userId=wallet_user.fd_id ')
                ->join('wallet_group_purse ','wallet_purse.fd_id=wallet_group_purse.purse_id ')
                ->select();

        }
        /*$re=Db::name('wkj_user_coinsbc')->getLastSql();
        var_dump($re);die;*/

        if(empty($info)){
            return jorx(['code' => 201,'msg' => '数据为空!','data' => $info]);
        }else{
            return jorx(['code' => 200,'msg' => '获取成功!','data' => $info]);
        }

    }




    /*
     *
     * BTC和SDT汇总(运维后台)
     * */
    public function getTotalData(Request $request)
    {

        $coin= $request->param('coin');

        if ($coin=='btc'){          //btc
            $info = Db::query('select sum(btc) as  total_balance,sum(btcd) as blocked_balance from wkj_user_coinbtc;');

        }else{   //sdt
            $group_id= $request->param('group_id');

            if($group_id!=1&&$group_id!='all'){
                $sql='select sum(sdt.sbc) as  sbc,sum(sdt.sbcd) as sbcd,wallet_group_purse.group_id from wkj_user_coinsbc as sdt INNER JOIN wallet_group_purse  on sdt.userid=wallet_group_purse.purse_id where wallet_group_purse.group_id='.$group_id.';';
                $info = Db::query($sql);
                $info[0]['group_id']=$group_id;
                if($info[0]['sbc']==null)
                    $info[0]['sbc']=0;
                if($info[0]['sbcd']==null)
                    $info[0]['sbcd']=0;
            }else{
                $info=[];
                //获取所有类型id
                $re=Db::name('wallet_group')->field('id')->where('id','neq',1)->select();
                foreach($re as $v){
                    $temp=[];
                    $temp['sbc']=0;
                    $temp['sbcd']=0;
                    $temp['group_id']=$v['id'];
                    $info[]=$temp;
                }

                $sql='select sdt.*,wallet_group_purse.group_id
                                        from wkj_user_coinsbc as sdt INNER JOIN wallet_group_purse  on sdt.userid=wallet_group_purse.purse_id
                                        ';
                $aa = Db::query($sql);


                //处理数据
                foreach($info as $k=>$v){
                    foreach($aa as $k1=>$v1){
                        if($v['group_id']==$v1['group_id']){
                            $info[$k]['sbc']= bcadd($v1['sbc'],$info[$k]['sbc'],10);
                            $info[$k]['sbcd']=bcadd($v1['sbcd'],$info[$k]['sbcd'],10);
                        }

                    }
                }

            }

        }

        if(empty($info)){
            return jorx(['code' => 201,'msg' => '数据为空!','data' => []]);
        }else{
            return jorx(['code' => 200,'msg' => '获取成功!','data' => $info]);
        }
    }




    /*
     * 修改用户钱包所属组
     * */
    public function editGroup(Request $request){
        $purse_id = $request->param('purse_id');
        $data['group_id'] = $request->param('group_id');
        $info=Db::table('wallet_group_purse')->where('purse_id', $purse_id)->update($data);
        return jorx(['code' => 200,'msg' => '获取成功!','data' => $info]);
    }



    /*
    * 修改组名称
    * */
    public function editGroupName(Request $request){
        $group_id = $request->param('group_id');
        $name = $request->param('name');
        $re=Db::table('wallet_group')->where(['id'=>$group_id])->setField('name',$name);
        if($re){
            return jorx(['code' => 200,'msg' => '修改成功!']);
        }else{
            return jorx(['code' => 201,'msg' => '修改失败!']);
        }

    }



    /*
    * 添加组
    * */
    public function addGroup(Request $request){
        $add_name = $request->param('name');

        $name=Db::table('wallet_group')->where(['name'=>$add_name])->select();

        if(empty($name)){
            $re=Db::name('wallet_group')->insert([
                'name'=>$add_name,
            ]);
            if($re){
                return jorx(['code' => 200,'msg' => '添加成功!']);
            }else{
                return jorx(['code' => 200,'msg' => '添加失败请排除原因!']);
            }

        }else{
            return jorx(['code' => 201,'msg' => '组名已存在']);
        }



    }



    /*获取组
     * */
    public function getGroup(Request $request){

        $info=Db::table('wallet_group')->select();
        return jorx(['code' => 200,'msg' => '获取成功','data'=>$info]);


    }






    /*
     *
     * 路由提取到钱包记录
     * */
    public function getRoutePruse(Request $request)
    {

        $start_time = $request->param('start_time');
        $end_time= $request->param('end_time');



        $time='';

        if ($start_time && $start_time){
            $sql="select sum(fd_sbcNums) as  total_balance from wallet_recharge where fd_createDate>= '".$start_time."' and  fd_createDate <= '".$end_time."' ;";

            $time=$start_time.'-'.$end_time;
        }else  if($start_time) {
            $sql="select sum(fd_sbcNums) as  total_balance  from wallet_recharge where fd_createDate >= '".$start_time."' ;";

            $time=$start_time;
        }else if($end_time) {
            $sql="select sum(fd_sbcNums) as  total_balance  from wallet_recharge where fd_createDate <= '".$end_time."' ;";
            $time=$end_time;
        }else{

            $sql="select sum(fd_sbcNums) as  total_balance from wallet_recharge ;";
            $time='all';
        }
        $info = Db::query($sql);

        if(empty($info)){
            return jorx(['code' => 201,'msg' => '数据为空!','data' => []]);
        }else{
            return jorx(['code' => 200,'msg' => '获取成功!','time'=>$time,'data' => $info]);
        }
    }



    /*
     *
     * BTC数据总览(运维后台)
     * */
    public function getBtcData(Request $request)
    {

        $user = $request->param('user');
        $limit= $request->param('limit');
        $page = $request->param('page');
        $limit= isset($limit) ? $limit : 10;
        $page = isset($page) ? $page : 1;


        $info = Db::name('wkj_user_coinbtc')
            ->field('wkj_user_coinbtc.*,wallet_user.fd_userName as user_name')
            ->alias('a')
            ->join('wallet_user','a.userid=wallet_user.fd_id ')
            ->select();


        if(empty($info)){
            return jorx(['code' => 201,'msg' => '数据为空!','data' => []]);
        }else{
            return jorx(['code' => 200,'msg' => '获取成功!','data' => $info]);
        }

    }



    /*
     * 创建官方用户和账号
     * */
    public function spuerWallet(Purse $purse)
    {

        $aa=Db::table('wallet_user')->where('fd_email','admin@starbridgechain.com')->find();

        if ($aa){
            return jorx(['code' => 400,'msg' => '账号已经存在!']);
        }else{


            // 启动事务
            Db::startTrans();
            try{

                $user=new User();//创建用户
                $salt = Str::random(6);//盐值
                $password = 'Admin123';
                $psd=Hash::make($password,'md5',['salt' => $salt]);
                $user->data([
                    'fd_email'          => 'admin@starbridgechain.com',
                    'fd_userName'       => 'superUser',
                    'fd_salt'           => $salt,
                    'fd_psd'            => $psd,
                    'fd_lastActivity'   => date('Y-m-d H:i:s'),
                    'fd_userCode'       => accessToken(),
                ])->save();

                //获取用户id
                $uer_id=$user->getLastInsID();

                //生成钱包唯一地址
                mt_srand( (double) microtime() * 10000);
                $str = $_SERVER['REQUEST_TIME_FLOAT'] . uniqid(mt_rand(1, 999999));
                $uni_str=strtolower('0x' .  md5($str) . '-super');

                $purse->data([
                    'fd_userId'         => $uer_id,             //用户id
                    'fd_walletAdr'      => $uni_str,            //钱包地址
                    'fd_psd'            => $psd,     //钱包密码（交易密码,默认登录密码）
                    'fd_salt'           => $salt,    //密码盐值
                    'fd_status'         => 1,                   //0锁定1正常
                    'fd_isRelieve'      => 1,                   //0解除绑定1绑定中
                    'fd_createDate' => date('Y-m-d H:i:s'),
                ])->save();


                //获取钱包id
                $purseId = $purse->getLastInsID();


                //官方超级钱包,初始化余额,注意userid对应的是钱包id
                Db::table('wkj_user_coinsbc')->insert([
                    'userid' => $purseId,
                    'sbc'=>300000000,
                ]);


                //创建10个子钱包
                $walle_Addr='0x000a5b9fc190cff707980a8f4def4000sgdgay';


                for ($i=1;$i<=10;$i++){
                    //生成钱包唯一地址
                    if ($i!=10){
                        $uni_str=strtolower($walle_Addr.'0'.$i);
                    }else{
                        $uni_str=strtolower($walle_Addr.$i);
                    }

                    //01账号是奖励账号，02账号是补偿账号
                    $data=[
                        'fd_userId'         => $uer_id,     //用户id
                        'fd_walletAdr'      => $uni_str,    //钱包地址
                        'fd_psd'            => $psd,        //钱包密码（交易密码,默认登录密码）
                        'fd_salt'           => $salt,      //密码盐值
                        'fd_status'         => 1,           //0锁定1正常
                        'fd_isRelieve'      => 1,           //0解除绑定1绑定中
                        'fd_createDate' => date('Y-m-d H:i:s'),
                    ];

                    Db::name('wallet_purse')->insert($data);
                    $child_purseId = Db::name('wallet_purse')->getLastInsID();

                    //初始化余额,注意userid对应的是钱包id
                    Db::table('wkj_user_coinsbc')->insert([
                        'userid' => $child_purseId,
                    ]);
                }


                Db::commit();
                return jorx(['code' => 200,'msg' => '创建成功!']);
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return jorx(['code' => 400,'msg' => '创建失败!']);
            }
        }
    }



    /*
     * 调用第三方接口转账
     * */
    public function postInter(Request $request){

        $num = floatval($request->param('num'));      //转账数量
        $type = $request->param('type');    //1是奖励，2是补偿
        $walle_Addr='0x000a5b9fc190cff707980a8f4def4000sgdgay';


        if($num <= 0)
        {
            return jorx(['code' => 400,'msg' => '请确认转账金额!']);
        }

        //生成订单唯一ID
        $order_no = createOrderNo();
        $data=[
            'num'=>$num,
            'from_addr'=>$walle_Addr.$type,
            'order_no'=>$order_no,
            'type'=>$type,
        ];

        //请求接口地址
        $url='https://apiportal.cmbcrouter.com:8016/api/AppWebApi/Operation_Recharge';
        //$url='http://1.82.184.50:8086/api/AppWebApi/Operation_Recharge';

        $Mobile='13122224444';
        $Appkey='13122224444'.date('Ymd');
        $result=[
            'Mobile'=>$Mobile,
            'Appkey'=>md5($Appkey),
            'Amount'=>$num,
            'OrderID'=>$order_no,
            'Type'=>$type,
        ];

        //返回值uuid
        $re=json_decode(Curl::post($url,json_encode($result),['Content-Type: application/json']));
        $uuid=$re->Body->Data->UUID;

        if ($re->Body->Data->Status==1){

            $begin= Db::name('wallet_purse')->where('fd_walletAdr' , $walle_Addr.'0'.$type)->find();

            if(empty($begin))   //如果钱包不存在,退出
                return jorx(['code' => 400,'msg' => '转账钱包不存在!']);



            $Surplus_num=Db::name('wkj_user_coinsbc')->where('userid' , $begin['fd_id'])->value('sbc');     //查看剩余余额


            if($Surplus_num<$num){  //查询转账钱包的余额
                return jorx(['code' => 400,'msg' => '余额不足']);
            }else{

                Db::startTrans();
                try {

                    $true = Db::name('wkj_user_coinsbc')->where('userid', $begin['fd_id'])->setField('sbc', ($Surplus_num - $num));  //修改账号余额

                    if ($true) {


                        $data['createTime'] = date('Y-m-d H:i:s');
                        $data['uuid'] = $uuid;
                        $ee = Db::name('wallet_transfer')->insert($data);


                        if ($ee) {
                            Db::commit();
                            return jorx(['code' => 200, 'msg' => '转账成功，日志写入成功!']);

                        } else {
                            Db::rollback();
                            return jorx(['code' => 200, 'msg' => '转账成功，日志写入失败!']);

                        }
                    } else {
                        return jorx(['code' => 400, 'msg' => '转账成功，日志写入失败!']);
                    }

                }catch (\Exception $e) {
                    Db::rollback();
                    return jorx(['code' => 400,'msg' => '转账成功，日志写入失败!']);
                }
            }
        }else{
            return jorx(['code' => 400,'msg' => '转账失败,路由器接口请求失败!']);
        }
    }


    /*修改btc分红基数*/
    public function updateBtc(Request $request)
    {
        $btc = $request->param('num','');


        $setting=Db::name('profit_syssetting')->find();
        if($btc=='')return jorx(['code' => 200,'msg' => '获取成功','当前btc分红基数'=>$setting['pss_btcBase']]);

        $re=Db::name('profit_syssetting')->where(['pss_id'=>$setting['pss_id']])->setField('pss_btcBase',$btc);

        if($re){
            return jorx(['code' => 200,'msg' => '修改成功','当前btc分红基数'=>$btc]);
        }else{
            return jorx(['code' => 201,'msg' => '修改失败','当前btc分红基数'=>$setting['pss_btcBase']]);
        }

    }

    /*修改页面显示分红上浮下午比例*/
    public function updateFloat(Request $request)
    {
        $floating_up = floatval($request->param('floating_up',''));
        $floating_down = floatval($request->param('floating_down',''));
        /*if($floating_down<0.99)return jorx(['code' => 201,'msg' => '下浮不能小于0.99']);
        if($floating_up>1.005)return jorx(['code' => 201,'msg' => '上浮不能大于1.05']);*/

        $setting=Db::table('profit_syssetting')->find();
        $info['floating_up']=$setting['floating_up'];
        $info['floating_down']=$setting['floating_down'];

        if($floating_up==''&&$floating_down=='')return jorx(['code' => 200,'msg' => '获取成功','当前浮动'=>$info]);

        $re=Db::name('profit_syssetting')->where(['pss_id'=>$setting['pss_id']])->update(['floating_up'=>$floating_up,'floating_down'=>$floating_down]);

        if($re){
            return jorx(['code' => 200,'msg' => '修改成功','当前浮动'=>'上浮：'.$floating_up.' 下浮:'.$floating_down]);
        }else{
            return jorx(['code' => 201,'msg' => '修改失败','当前浮动'=>'上浮：'.$setting['floating_up'].' 下浮:'.$setting['floating_down']]);
        }

    }
    /*修改sdt分红基数*/
    public function updateSdtBase(Request $request)
    {
        $sdt = $request->param('num','');
        $setting=Db::name('profit_syssetting')->find();

        if($sdt=='')return jorx(['code' => 200,'msg' => '获取成功','当前sdt分红基数'=>$setting['pss_dividendBase']]);

        $sdt=(int)$sdt;
        $re=Db::name('profit_syssetting')->where(['pss_id'=>$setting['pss_id']])->setField('pss_dividendBase',$sdt);

        if($re){
            return jorx(['code' => 200,'msg' => '修改成功','当前sdt分红基数'=>$sdt]);
        }else{
            return jorx(['code' => 201,'msg' => '修改失败','当前sdt分红基数'=>$setting['pss_dividendBase']]);
        }

    }

    /*增加入池设备数量*/
    public function addEquipmentNum(Request $request){
        $batch = $request->param('batch',1810);
        $num = $request->param('num',10);
        $setting=Db::table('profit_newuser_setting')->where(['type'=>1,'batch'=>$batch])->find();
        if($num=='')return jorx(['code' => 200,'msg' => '获取成功','当前设备数'=>$setting['add_num']]);

        if($setting==null){
            return jorx(['code' => 201,'msg' => '记录不存在!']);
        }
        $num_aa=bcadd($num,$setting['add_num']);
        $re=Db::table('profit_newuser_setting')->where(['type'=>1,'batch'=>$batch])->setField('add_num',$num_aa);

        $add_num=Db::table('profit_newuser_setting')->field('add_num')->where(['type'=>1,'batch'=>$batch])->find();
        if($re){
            return jorx(['code' => 200,'msg' => '成功!','data'=>$add_num['add_num']]);
        }else{
            return jorx(['code' => 201,'msg' => '失败,请重试!']);
        }


    }

    /*修改新设备分红基数*/
    public function updateNewBtc(Request $request)
    {
        $btc = $request->param('num');

        $setting=Db::name('profit_newuser_setting')->find();
        if($btc=='')return jorx(['code' => 200,'msg' => '获取成功','当前新设备btc分红基数'=>$setting['btc_base']]);

        $re=Db::name('profit_newuser_setting')->where(['id'=>$setting['id']])->setField('btc_base',$btc);

        if($re){
            return jorx(['code' => 200,'msg' => '修改成功','当前新设备btc分红基数'=>$btc]);
        }else{
            return jorx(['code' => 201,'msg' => '修改失败','当前新设备btc分红基数'=>$setting['btc_base']]);
        }

    }

    /*修改页面显示分红上浮下午比例*/
    public function updateNewFloat(Request $request)

    {
        $floating_up = floatval($request->param('floating_up',''));
        $floating_down = floatval($request->param('floating_down',''));
        /*if($floating_down<0.99)return jorx(['code' => 201,'msg' => '下浮不能小于0.99']);
        if($floating_up>1.005)return jorx(['code' => 201,'msg' => '上浮不能大于1.05']);*/

        $setting=Db::table('profit_newuser_setting')->where(['type'=>1,'batch'=>1810])->find();
        $info['floating_up']=$setting['floating_up'];
        $info['floating_down']=$setting['floating_down'];
        if($floating_up==''&&$floating_down=='')return jorx(['code' => 200,'msg' => '获取成功','当前浮动'=>$info]);
        $re=Db::name('profit_newuser_setting')->where(['id'=>$setting['id']])->update(['floating_up'=>$floating_up,'floating_down'=>$floating_down]);
        if($re){
            return jorx(['code' => 200,'msg' => '修改成功','当前浮动'=>'上浮：'.$floating_up.' 下浮:'.$floating_down]);
        }else{
            return jorx(['code' => 201,'msg' => '修改失败','当前浮动'=>'上浮：'.$setting['floating_up'].' 下浮:'.$setting['floating_down']]);
        }

    }


    /*锁仓快照系数*/
    public function updateCoefficient(Request $request)
    {
        $coefficient = $request->param('coefficient','');
        $setting=Db::table('profit_syssetting')->limit(1)->select();

        if($coefficient==''){
            return jorx(['code' => 201,'msg' => '获取成功','data'=>$setting[0]['coefficient']]);
        }
        if((int)$coefficient<=0){
            return jorx(['code' => 201,'msg' => '修改失败,系数不能为空或者<=0']);
        }

        $re=Db::name('profit_syssetting')->where(['pss_id'=>$setting[0]['pss_id']])->setField('coefficient',(int)$coefficient);
        if($re){
            return jorx(['code' => 200,'msg' => '修改成功','当前系数'=>$coefficient]);
        }else{
            return jorx(['code' => 201,'msg' => '修改失败','当前系数'=>$setting[0]['coefficient']]);
        }

    }

    /*锁仓基数及生效时间*/
    public function insertLockbase(Request $request)
    {
        $sdtBase = $request->param('sdtBase','');

        if($sdtBase==''){
            $re=Db::table('profit_setting_lock')->select();
            return jorx(['code' => 200,'data' => $re]);
        }
        if((int)$sdtBase<=0){
            return jorx(['code' => 201,'msg' => '锁仓基数不能为空 或者<=0']);

        }
        $re=Db::table('profit_setting_lock')->insert([
            'sdtBase'=>$sdtBase,
            'batch'=>$sdtBase,
            'createTime'=>date("Y-m-d H:i:s"),
        ]);
        if($re){
            return jorx(['code' => 200,'msg' => '添加成功']);
        }else{
            return jorx(['code' => 201,'msg' => '添加失败']);
        }

    }

    /*查询钱包和路由账号绑定关系查询*/
    public function  selectMobile(Request $request){
        $email = $request->param('email','');
        $mobile = $request->param('mobile','');
        $type = $request->param('type','');
        $limit = $request->param('limit');
        $page = $request->param('page');
        if($limit ==''|| (int)$limit<=0)
            $limit=10;
        if($page==''|| (int)$page<=0)
            $page=1;

        $where=[];
        if($email !='')
            $where['wallet_user.fd_email']=$email;
        if($mobile !='')
            $where['profit_purse_mobile.mobile_num']=$mobile;
        if($type!='')
            $where['profit_purse_mobile.type']=$type;

        $info=Db::table('profit_purse_mobile')
            ->field('wallet_user.fd_email,wallet_user.fd_sbcNums,profit_purse_mobile.mobile_num,profit_purse_mobile.user_id,profit_purse_mobile.id')
            ->join('wallet_user','wallet_user.fd_id=profit_purse_mobile.user_id','left')
            ->where($where)
            ->limit(((int)$page-1)*10,(int)$limit)
            ->select();
        $count=Db::table('profit_purse_mobile')
            ->field('wallet_user.fd_email,wallet_user.fd_sbcNums,profit_purse_mobile.mobile_num,profit_purse_mobile.user_id,profit_purse_mobile.id')
            ->join('wallet_user','wallet_user.fd_id=profit_purse_mobile.user_id','left')
            ->where($where)
            ->count();

        return jorx(['code' => 200,'msg' => '获取成功','count'=>$count,'data'=>$info]);

    }

    /*下载绑定关系*/
    public  function updaloadMobile(Request $request){
        $email = $request->param('email','');
        $mobile = $request->param('mobile','');
        $type = $request->param('type','');
        $where=[];
        if($email !='')
            $where['wallet_user.fd_email']=$email;
        if($mobile !='')
            $where['profit_purse_mobile.mobile_num']=$mobile;
        if($type!='')
            $where['profit_purse_mobile.type']=$type;

        $info=Db::table('profit_purse_mobile')
            ->field('wallet_user.fd_email,wallet_user.fd_sbcNums,profit_purse_mobile.mobile_num,profit_purse_mobile.user_id,profit_purse_mobile.id')
            ->join('wallet_user','wallet_user.fd_id=profit_purse_mobile.user_id','left')
            ->where($where)
            ->select();


        //重组数组  处理数据
        $out_data=[];
        foreach ($info as $v) {
            $out_data[] = [
                $v['id'],
                $v['fd_email'],
                $v['mobile_num'],
                $v['fd_sbcNums'],
                $v['user_id'],
            ];
        }
        //设置头
        $header = ['id','Email','电话','总资产','用户id'];
        //latter
        $latter = ['A','B','C','D','E'];
        //单元格宽度
        $width = [10,30,20,25];
        //文件名
        $filename = '绑定关系查询'.'.xls';
        //导出
        PHPExcelController::excelExport($out_data,$header,$latter,$width,$filename);

    }


    /*锁仓数据展示及查询*/
    public function  showLock(Request $request){
        $email = $request->param('email','');
        $start_time = $request->param('start_time','');
        $end_time = $request->param('end_time','');
        $time_type = $request->param('time_type','');
        $type = $request->param('type','');
        $status = $request->param('status','');

        $limit = $request->param('limit');
        $page = $request->param('page');
        if($limit ==''|| (int)$limit<=0)
            $limit=10;
        if($page==''|| (int)$page<=0)
            $page=1;

        $where=[];
        if($email !='')
            $where['wallet_user.fd_email']=$email;
        if($type !='')
            $where['wallet_sdt_lock.type']=$type;
        if($status !='')
            $where['wallet_sdt_lock.status']=$status;

        if($start_time!=''){
            if($time_type=='lock'){
                $where['wallet_sdt_lock.create_time'] = ['egt' ,$start_time];
            }else{
                $where['wallet_sdt_lock.lock_time'] = ['egt' ,$start_time];
            }

        }

        if($end_time!=''){
            if($time_type=='lock'){
                $where['wallet_sdt_lock.create_time'] = ['elt' ,$end_time];
            }else{
                $where['wallet_sdt_lock.lock_time'] = ['elt' ,$start_time];
            }
        }

        if($start_time!=''&&$end_time!=''){
            if($time_type=='lock'){
                 $where['wallet_sdt_lock.create_time'] = ['between' ,[$start_time, $end_time]];
            }else{
                $where['wallet_sdt_lock.lock_time'] = ['between' ,[$start_time, $end_time]];
            }

        }


        $info=Db::table('wallet_sdt_lock')
            ->field('wallet_user.fd_userName,wallet_sdt_lock.id,wallet_sdt_lock.user_id,wallet_sdt_lock.lock_nums,wallet_sdt_lock.lock_time,wallet_sdt_lock.type,wallet_sdt_lock.status,wallet_sdt_lock.batch,wallet_sdt_lock.create_time')
            ->join('wallet_user','wallet_user.fd_id=wallet_sdt_lock.user_id','left')
            ->where($where)
            ->limit(((int)$page-1)*10,(int)$limit)
            ->select();
        $count=Db::table('wallet_sdt_lock')
            ->field('wallet_user.fd_userName,wallet_sdt_lock.id,wallet_sdt_lock.user_id,wallet_sdt_lock.lock_nums,wallet_sdt_lock.lock_time,wallet_sdt_lock.type,wallet_sdt_lock.status,wallet_sdt_lock.batch,wallet_sdt_lock.create_time')
            ->join('wallet_user','wallet_user.fd_id=wallet_sdt_lock.user_id','left')
            ->where($where)
            ->count();

        return jorx(['code' => 200,'msg' => '获取成功','count'=>$count,'data'=>$info]);

    }

    /*下载锁仓数据*/
    public function  uploadLock(Request $request){
        $email = $request->param('email','');
        $start_time = $request->param('start_time','');
        $end_time = $request->param('end_time','');
        $time_type = $request->param('time_type','');
        $type = $request->param('type','');
        $status = $request->param('status','');



        $where=[];
        if($email !='')
            $where['wallet_user.fd_email']=$email;
        if($type !='')
            $where['wallet_sdt_lock.type']=$type;
        if($status !='')
            $where['wallet_sdt_lock.status']=$status;

        if($start_time!=''){
            if($time_type=='lock'){
                $where['wallet_sdt_lock.create_time'] = ['egt' ,$start_time];
            }else{
                $where['wallet_sdt_lock.lock_time'] = ['egt' ,$start_time];
            }

        }

        if($end_time!=''){
            if($time_type=='lock'){
                $where['wallet_sdt_lock.create_time'] = ['elt' ,$end_time];
            }else{
                $where['wallet_sdt_lock.lock_time'] = ['elt' ,$start_time];
            }
        }

        if($start_time!=''&&$end_time!=''){
            if($time_type=='lock'){
                $where['wallet_sdt_lock.create_time'] = ['between' ,[$start_time, $end_time]];
            }else{
                $where['wallet_sdt_lock.lock_time'] = ['between' ,[$start_time, $end_time]];
            }

        }


        $info=Db::table('wallet_sdt_lock')
            ->field('wallet_user.fd_userName,wallet_sdt_lock.id,wallet_sdt_lock.user_id,wallet_sdt_lock.lock_nums,wallet_sdt_lock.lock_time,wallet_sdt_lock.type,wallet_sdt_lock.status,wallet_sdt_lock.batch,wallet_sdt_lock.create_time')
            ->join('wallet_user','wallet_user.fd_id=wallet_sdt_lock.user_id','left')
            ->where($where)
            ->select();

        //重组数组  处理数据
        $out_data=[];
        foreach ($info as $v) {
            $out_data[] = [
                $v['id'],
                $v['fd_userName'],
                $v['user_id'],
                $v['lock_nums'],
                $v['lock_time'],
                $v['type'],
                $v['status'],
                $v['batch'],
                $v['create_time'],
            ];
        }
        //设置头
        $header = ['id','用户名','用户id','锁仓数量','锁仓时间','续期(1是续期，2是不续期)','删除(1是正常，2是删除)','批次','创建时间'];
        //latter
        $latter = ['A','B','C','D','E','F','G','H','I'];
        //单元格宽度
        $width = [10,30,20,25,25,25,25,25,25];
        //文件名
        $filename = '锁仓列表'.'.xls';
        //导出
        PHPExcelController::excelExport($out_data,$header,$latter,$width,$filename);

    }





    public function upload(Request $request)
    {

        $type      = $request->param('type');
        $path = 'download';
        if($type=='ios'){
            $re=move_uploaded_file($_FILES["file"]["tmp_name"],$path.'/'.'SDT.ipa');
        }else if($type=='android'){
            $re=move_uploaded_file($_FILES["file"]["tmp_name"],$path.'/'.'Android-Release.apk');
        }else{
            $result['status'] = 201;
            $result['msg'] = '请确认上传包类型,是ios还是android';
            return json($result);
        }

        if($re){
            $result['status'] = 200;
            $result['msg'] = '上传成功'.$type;
            return json($result);
        }else{
            $result['status'] = 201;
            $result['msg'] = '上传失败'.$type;
            return json($result);
        }


    }

}


