<?php


namespace app\wallet\controller;


use app\wallet\model\Order;
use think\Config;
use think\Db;
use think\Request;

class OrderController extends BaseController
{
    /**
     * 获取全部订单
     * @param Request $request
     * @param Order $order
     * @return \think\response\Json
     */
    public function getListAll(Request $request,Order $order)
    {

        $field = [
            'wallet_order.fd_id as orderId',
            'wallet_user.fd_userName as username',
            'wallet_purse.fd_walletAdr as wallet',
            'wallet_order_log.tip',
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
//            'wallet_order.fd_real_goneNums as realGoneNum',
            'wallet_order.fd_status as status',
            'wallet_order.fd_createDate as createDate',
            'wallet_order.fd_operator as operator',
            'wallet_order.fd_pictureAdr as pictureAdr',
        ];
        $param = $request->param();
        $order = $order
            ->join('wallet_user','wallet_order.fd_userId = wallet_user.fd_id','LEFT')
            ->join('wallet_purse','wallet_order.fd_walletId = wallet_purse.fd_id','LEFT')
            ->join('wallet_order_log','wallet_order.fd_orderNo = wallet_order_log.order_no','LEFT')
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
        $limit     = isset($param['limit']) ? $param['limit'] : 10;
        $page      = isset($param['page']) ? $param['page'] : 1;
        $data      = $order->page($page,$limit)->order('wallet_order.fd_createDate','desc')->select();
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

        return jorx(['code' => 200,'msg' => '获取成功!','count' => $count,'data' => $data]);
    }


    /**
     * 获取用户btc充值/提现记录(旧版本)
     * @param Request $request
     * @param Order $order
     * @return \think\response\Json
     */
    public function getList(Request $request,Order $order)
    {
        $field = [
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
//            'wallet_order.fd_real_goneNums as realGoneNum',
            'wallet_order.fd_status as status',
            'wallet_order.fd_createDate as createDate',
            'wallet_order.fd_pictureAdr as pictureAdr',
        ];
        $user  = $request->user;
        $param = $request->param();
        $order = $order
            ->join('wallet_user','wallet_order.fd_userId = wallet_user.fd_id','LEFT')
            ->join('wallet_purse','wallet_order.fd_walletId = wallet_purse.fd_id','LEFT')
            ->field($field);
        $order = $order->where('wallet_order.fd_userId',$user['fd_id']);
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
        $order = $order->order('wallet_order.fd_createDate','desc')->select();
        $map   = [
            'publicWallet' => '公网钱包',
            'wechat'       => '微信',
            'bankcard'     => '银行卡'
        ];
        //修改来源和去向
        foreach ($order as $item) {
            $item->pictureAdr && $item->pictureAdr = $request->root(true) . $item->pictureAdr;
            isset($map[$item->gone])   && $item->gone   = $map[$item->gone];
            isset($map[$item->source]) && $item->source = $map[$item->source];
            $item->sbcPrice          = n_f(number_format( $item->sbcPrice - 0,10,'.',''));
            $item->sourceNum         = n_f(number_format( $item->sourceNum - 0,10,'.',''));
            $item->goneNum           = n_f(number_format( $item->goneNum - 0,10,'.',''));
            $item->fee               = n_f(number_format( $item->fee - 0,10,'.',''));
            !$item->wallet && $item->wallet = '';
        }
        if(empty($order)){
            return jorx(['code' => 201,'msg' => '数据为空!','data' => []]);
        }
        return jorx(['code' => 200,'msg' => '获取成功!','data' => $order]);
    }


    
    /**
     * 获取用户btc充值/提现记录(新版本)
     * @param Request $request
     * @param Order $order
     * @return \think\response\Json
     */
    public function newgetList(Request $request,Order $order)
    {
        $field = [
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
            'wallet_order.fd_status as status',
            'wallet_order.fd_createDate as createDate',
            'wallet_order.fd_pictureAdr as pictureAdr',
        ];

        $user  = $request->user;
        $param = $request->param();
        $order = $order
            ->join('wallet_user','wallet_order.fd_userId = wallet_user.fd_id','LEFT')
            ->join('wallet_purse','wallet_order.fd_walletId = wallet_purse.fd_id','LEFT')
            ->field($field);
        $order = $order->where('wallet_order.fd_userId',$user['fd_id']);

        $limit = $request->param('limit');
        $page = $request->param('page');

        if($limit ==''|| (int)$limit<=0)
            $limit=10;
        if($page==''|| (int)$page<=0)
            $page=1;

        //分页
        //$order->page($page,$limit);
        $order->limit(((int)$page-1)*10,(int)$limit);
       // $order->page($param['page'],$param['limit']);


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

        $order_bak =   clone $order;
        $count     = $order_bak->count('wallet_order.fd_id');
//	var_dump($page);
//	var_dump($count);die;
 //       $limit     = isset($param['limit']) ? $param['limit'] : 10;
  //      $page      = isset($param['page']) ? $param['page'] : 1;

        $order = $order->order('wallet_order.fd_createDate','desc')->select();
        $map   = [
            'publicWallet' => '公网钱包',
            'wechat'       => '微信',
            'bankcard'     => '银行卡'
        ];

        //修改来源和去向
        foreach ($order as $item) {
            $item->pictureAdr && $item->pictureAdr = $request->root(true) . $item->pictureAdr;
            isset($map[$item->gone])   && $item->gone   = $map[$item->gone];
            isset($map[$item->source]) && $item->source = $map[$item->source];
            $item->sbcPrice          = n_f(number_format( $item->sbcPrice - 0,10,'.',''));
            $item->sourceNum         = n_f(number_format( $item->sourceNum - 0,10,'.',''));
            $item->goneNum           = n_f(number_format( $item->goneNum - 0,10,'.',''));
            $item->fee               = n_f(number_format( $item->fee - 0,10,'.',''));
            !$item->wallet && $item->wallet = '';
        }


        if(empty($order)){
            return jorx(['code' => 201,'msg' => '数据为空!','data' => []]);
        }

        return jorx(['code' => 200,'msg' => '获取成功!','count'=>$count,'data' => $order]);

    }



    /**
     * 修改订单状态
     * @param Request $request
     * @param Order $order
     * @return \think\response\Json
     */
    public function changeStatus(Request $request,Order $order)
    {
        $orderId   = $request->param('orderId',0) - 0;
        $status    = $request->param('status','');
        $operator  = $request->param('operator','');
        $order   = $order->whereOr('fd_id',$orderId)->find();


        if(!$operator){
            return jorx(['code' => 400,'msg' => '操作人不能为空!']);
        }
        if('' === $status){
            return jorx(['code' => 400,'msg' => '修改状态不能为空!']);
        }
        if(1 != $status && 0 != $status){
            return jorx(['code' => 400,'msg' => '订单状态只能是0（到账）或1（未到账）!']);
        }
        if(!$order){
            return jorx(['code' => 400,'msg' => '订单不存在!']);
        }
        $order->fd_status   = $status;
        $order->fd_operator = $operator;
        if(false !== $order->save()){
            return jorx(['code' => 200,'msg' => '修改成功!']);
        }
        return jorx(['code' => 400,'msg' => '修改失败，请稍后再试!']);
    }



    /**
     * 获取sdt转账记录(新版本)
     * @param Request $request
     * @return \think\response\Json
     */
    public function newgetSdtTransferLogList(Request $request)
    {
        $user = $request->user;
        $start_time = $request->param('start_time');
        $end_time = $request->param('end_time');
        $from_type = $request->param('from_type');


        $limit = $request->param('limit');
        $page = $request->param('page');
        if($limit ==''|| (int)$limit<=0)
            $limit=10;
        if($page==''|| (int)$page<=0)
            $page=1;
//	var_dump($limit);
//	var_dump($page);die;
        $where = [];
        if($start_time && $end_time)
            $where['a.created_time'] = ['between' ,[$start_time . ' 00:00:00', $end_time . ' 23:59:59']];

        $type=3;
        if($from_type == 'iin'){        //iin 转入
            $where['fd_col_user_id'] = ['eq' , $user['fd_id']];
            $where['fd_user_id'] = ['neq' , $user['fd_id']];
            $type=0;
        } else if($from_type == 'out') {        //out 转出
            $where['fd_user_id'] = ['eq' , $user['fd_id']];
            $where['fd_col_user_id'] = ['neq' , $user['fd_id']];
            $type=1;
        } else if ($from_type == 'ext'){        //ext余额自转
            $where['fd_user_id & fd_col_user_id'] = ['eq' , $user['fd_id']];
            $type=2;
        } else {                               //all 全部
            $where['fd_user_id|fd_col_user_id'] = ['eq' , $user['fd_id']];
        }

        $info = Db::name('wallet_transfer_log')
            ->field('a.*,user.fd_userName as from_name,userA.fd_userName as to_name')
            ->alias('a')
            ->join('wallet_user user','a.fd_user_id=user.fd_id ','LEFT')
            ->join('wallet_user userA','a.fd_col_user_id=userA.fd_id ','LEFT')
            ->where($where)
            ->order('created_time','desc')
            ->limit(((int)$page-1)*10,(int)$limit)
 	    ->select();
//var_dump($page);
//var_dump($limit);
//var_dump(((int)$page-1)*10);
//var_dump((int)$limit*(int)$page);
//echo Db::name('wallet_transfer_log')->getLastSql();die;
        $count = Db::name('wallet_transfer_log')
            ->field('wallet_transfer_log.*,user.fd_userName as from_name,userA.fd_userName as to_name')
            ->alias('a')
            ->join('wallet_user user','a.fd_user_id=user.fd_id ','LEFT')
            ->join('wallet_user userA','a.fd_col_user_id=userA.fd_id ','LEFT')
            ->where($where)
            ->order('created_time','desc')
            ->count();
        $data = [];
        foreach ($info as $key => $value)
        {

            $value['fd_transfer_sbc'] = n_f(number_format( $value['fd_transfer_sbc']-0,10,'.',''));


            $data[$key]['fd_begin_walletAdr'] = $value['fd_begin_walletAdr'];
            $data[$key]['fd_end_walletAdr'] = $value['fd_end_walletAdr'];
            $data[$key]['fd_transfer_sbc'] =$value['fd_transfer_sbc'];

            $from_name=explode('@',$value['from_name']);
            if(isset($from_name[1])){
                $aa=substr($from_name[0],0,strlen($from_name[0])-3);
                $from_name_temp=$aa.'***@'.$from_name[1];
            }else{
                $aa=substr($from_name[0],0,strlen($from_name[0])-3);
                $from_name_temp=$aa.'***';
            }

            $to_name=explode('@',$value['to_name']);
            if(isset($to_name[1])){
                $aa=substr($to_name[0],0,strlen($to_name[0])-3);
                $to_name_temp=$aa.'***@'.$to_name[1];
            }else{
                $aa=substr($to_name[0],0,strlen($to_name[0])-3);
                $to_name_temp=$aa.'***';
            }

            $data[$key]['begin_name'] =$from_name_temp;
            $data[$key]['end_name'] =$to_name_temp;

            $data[$key]['fd_fee'] =$value['fd_fee'];

            if($type!=3){
                $data[$key]['fd_type'] = $type;
            }else{

                if($value['fd_user_id']!=$user['fd_id'] && $value['fd_col_user_id']==$user['fd_id']){
                    $data[$key]['fd_type'] = 0;
                }else if($value['fd_user_id']==$user['fd_id'] && $value['fd_col_user_id']!=$user['fd_id']){
                    $data[$key]['fd_type'] = 1;
                }else if($value['fd_user_id']==$user['fd_id'] && $value['fd_col_user_id']==$user['fd_id']){
                    $data[$key]['fd_type'] = 2;
                }
            }
            $data[$key]['order_no'] = $value['order_no'];
            $data[$key]['created_time'] = $value['created_time'];
        }


        if(empty($info)){
            return jorx(['code' => 201,'msg' => '数据为空!','data' => []]);
        }
        return jorx(['code' => 200,'msg' => '获取成功!','count'=>$count,'data' => $data]);
    }

    public function getSdtTransferLogList(Request $request)
    {
        $user = $request->user;
        $start_time = $request->param('start_time');
        $end_time = $request->param('end_time');
        $from_type = $request->param('from_type'); //in 转入 out 转出 all 全部
        $where = [];
        if($start_time)
            $where['created_time'] = ['>=' , $start_time . ' 00:00:00'];
        if($end_time)
            $where['created_time'] = ['<=' , $end_time . ' 23:59:59'];
        if($from_type == 'iin')
        {
            $where['fd_col_user_id'] = ['eq' , $user['fd_id']];
        }
        else if($from_type == 'out')
        {
            $where['fd_user_id'] = ['eq' , $user['fd_id']];
        }
        else
        {
            $where['fd_user_id|fd_col_user_id'] = ['eq' , $user['fd_id']];
        }
        $info = Db::name('wallet_transfer_log')
            ->where($where)
            ->order('created_time','desc')
            ->select();
        $data = [];
        foreach ($info as $key => $value)
        {
            $value['fd_transfer_sbc'] = n_f(number_format( $value['fd_transfer_sbc']-0,10,'.',''));

            if($value['fd_user_id'] == $user['fd_id'] && $value['fd_col_user_id'] != $user['fd_id'])//转出
            {
                $data[$key]['fd_walletAdr'] = $value['fd_end_walletAdr'];
                $data[$key]['fd_type'] = 0;
                $data[$key]['fd_transfer_sbc'] = '-' . $value['fd_transfer_sbc'] . 'sdt';
            }
            else if ($value['fd_col_user_id'] == $user['fd_id'] && $value['fd_user_id'] != $user['fd_id'])//转入
            {
                $data[$key]['fd_walletAdr'] = $value['fd_begin_walletAdr'];
                $data[$key]['fd_type'] = 1;
                $data[$key]['fd_transfer_sbc'] = '+' . $value['fd_transfer_sbc'] . 'sdt';
            }
            else if($value['fd_col_user_id'] == $user['fd_id'] && $value['fd_user_id'] == $user['fd_id'])//余额整理
            {
                $data[$key]['fd_walletAdr'] = $value['fd_end_walletAdr'];
                $data[$key]['fd_type'] = 2;
                $data[$key]['fd_transfer_sbc'] = '+' . $value['fd_transfer_sbc'] . 'sdt';
            }
            $data[$key]['order_no'] = $value['order_no'];
            $data[$key]['created_time'] = $value['created_time'];
        }
        if(empty($info)){
            return jorx(['code' => 201,'msg' => '数据为空!','data' => []]);
        }
        return jorx(['code' => 200,'msg' => '获取成功!','data' => $data]);
    }



    /**
     * 提现撤回
     * @return \think\response\Json
     */
    public function withdrawOrder(Request $request)
    {
        $user = $request->user;
        $order_no = Request::instance()->post('fd_orderNo');
        if($order_no)
        {
            $user_id = Db::name('wallet_order')->whereIn('fd_status' ,'1')->where('fd_orderNo' , $order_no)->value('fd_userId');
            if($user_id)
            {
                if($user['fd_id'] == $user_id)
                {
                    $status = Db::name('wallet_order')
                        ->where('fd_orderNo' , $order_no)
                        ->update(
                            [
                                'fd_status' => 2,
                                'fd_updateDate' => date('Y-m-d H:i:s')
                            ]
                        );
                    if($status)
                    {
                        $result = [
                            'msg' => '操作成功',
                            'fd_status'=>2,
                            'status' => 200,
                        ];
                    }
                    else
                    {
                        $result = [
                            'msg' => '操作失败',
                            'fd_status'=>1,
                            'status' => 201,
                        ];
                    }
                }
                else
                {
                    $result = [
                        'msg' => '非法操作',
                        'status' => 201
                    ];
                }
            }
            else
            {
                $result = [
                    'msg' => '该订单已撤回或正在处理中，请勿重复提交！',
                    'status' => 201
                ];
            }
        }
        else
        {
            $result = [
                'msg' => '请验证参数',
                'status' => 201
            ];
        }
        return json($result);
    }


    /**
     * 撤回审核
     * @param Request $request
     * @return \think\response\Json
     */
    public function authOrder(Request $request)
    {
        $type = $request->param('type');
        $admin_id = $request->param('admin');
        $tip = $request->param('tip');
        $order_no = $request->param('fd_orderNo');


        if (empty($type)) {
            return json([
                'msg' => '请选择类型',
                'status' => 201
            ]);
        }


        if (empty($order_no)) {
            return json([
                'msg' => '请选择订单',
                'status' => 201
            ]);
        }


        if (empty($admin_id)) {
            return json([
                'msg' => '未知错误',
                'status' => 201
            ]);
        }


        $info = Db::name('wallet_order')->where('fd_orderNo', $order_no)->where('fd_type' , 'out')->where('fd_status', 2)->find();


        if (empty($info)) {
            return json([
                'msg' => '记录不存在',
                'status' => 201
            ]);
        }
        Db::startTrans();
        try {
            if ($type == 'success') {
		
	//var_dump($info);
	//var_dump($info['fd_userId']);die;
                Db::table('wkj_user_coinbtc')->where('userid', $info['fd_userId'])->setInc('btc', $info['fd_fromNums']);
                $sbc = Db::name('wallet_user')->where('fd_id' , $info['fd_userId'])->value('fd_sbcNums');
                $sbc = bcadd($sbc , $info['fd_fromNums'] , 10);
                Db::name('wallet_user')->where('fd_id' , $info['fd_userId'])->setField('fd_sbcNums' , $sbc);


                Db::name('wallet_order')->where('fd_orderNo', $order_no)->update(['fd_status' => 3]);
                $ins_data['order_no'] = $order_no;
                $ins_data['wallet_id'] = $info['fd_walletId'];
                $ins_data['user_id'] = $info['fd_userId'];
                $ins_data['admin'] = $admin_id;
                $ins_data['num'] = $info['fd_fromNums'];
                $ins_data['status'] = 1;
                $ins_data['type'] = 'out';
                $ins_data['created_time'] = date('Y-m-d H:i:s');
                Db::name('wallet_order_log')->insert($ins_data);
                $result = [
                    'msg' => '操作成功',
                    'status' => 200
                ];
                Db::commit();


            } else if ($type == 'error') {

                Db::name('wallet_order')->where('fd_orderNo', $order_no)->update(['fd_status' => 4]);

                $ins_data['order_no'] = $order_no;
                $ins_data['wallet_id'] = $info['fd_walletId'];
                $ins_data['user_id'] = $info['fd_userId'];
                $ins_data['admin'] = $admin_id;
                $ins_data['num'] = $info['fd_fromNums'];
                $ins_data['status'] = 0;
                $ins_data['type'] = 'out';
                $ins_data['created_time'] = date('Y-m-d H:i:s');
                $ins_data['tip'] = $tip;

                Db::name('wallet_order_log')->insert($ins_data);

                $result = [
                    'msg' => '操作成功',
                    'status' => 200
                ];
                Db::commit();
            }
        } catch (\Exception $e) {
            Db::rollback();
            $result = [
                'msg' => '操作失败',
                'status' => 201,
            ];
        }
        return json($result);
    }

    /**
     * 充值审核
     * @param Request $request
     * @return \think\response\Json
     */
    public function authRecharge(Request $request)
    {
        $admin_id = $request->param('admin');
        $tip = $request->param('tip');
        $order_no = $request->param('fd_orderNo');
        if($admin_id && $order_no)
        {
            $info = Db::name('wallet_order')->where('fd_orderNo' , $order_no)->where('fd_type' , 'in')->where('fd_status' , 1)->find();
            if($info)
            {
                Db::startTrans();
                try{
                    Db::name('wallet_order')->where('fd_orderNo', $order_no)->update(['fd_status' => 5]);
                    $ins_data['order_no'] = $order_no;
                    $ins_data['wallet_id'] = $info['fd_walletId'];
                    $ins_data['user_id'] = $info['fd_userId'];
                    $ins_data['admin'] = $admin_id;
                    $ins_data['num'] = $info['fd_fromNums'];
                    $ins_data['status'] = 0;
                    $ins_data['type'] = 'in';
                    $ins_data['created_time'] = date('Y-m-d H:i:s');
                    $ins_data['tip'] = $tip;
                    Db::name('wallet_order_log')->insert($ins_data);
                    Db::commit();
                    $result = [
                        'msg' => '操作成功',
                        'status' => 200
                    ];
                }
                catch (\Exception $e)
                {
                    $result = [
                        'msg' => '操作失败',
                        'status' => 201
                    ];
                    Db::rollback();
                }
            }
            else
            {
                $result = [
                    'msg' => '操作失败',
                    'status' => 201,
                ];
            }
        }
        return json($result);
    }

    /**
     * 提现审核
     * @param Request $request
     * @return \think\response\Json
     */
    public function authOut(Request $request)
    {
        $type = $request->param('type');
        $admin_id = $request->param('admin_id');
        $tip = $request->param('tip');
        $order_no = $request->param('fd_orderNo');
        if (empty($type)) {
            return json([
                'msg' => '请选择类型',
                'status' => 201
            ]);
        }
        if (empty($order_no)) {
            return json([
                'msg' => '请选择订单',
                'status' => 201
            ]);
        }
        if ($admin_id) {
            return json([
                'msg' => '非法操作',
                'status' => 201
            ]);
        }
        $info = Db::name('wallet_order')->where('fd_orderNo', $order_no)->where('fd_type' , 'out')->where('fd_status', 1)->find();
        if (empty($info)) {
            return json([
                'msg' => '非法操作',
                'status' => 201
            ]);
        }
        Db::startTrans();
        try
        {
            if($type == 'yes')
            {
                Db::table('wkj_user_coinbtc')->where('userid', $info['fd_userId'])->setInc('btc', $info['fd_fromNums']);
                $sbc = Db::name('wallet_user')->where('fd_id' , $info['fd_userId'])->value('fd_sbcNums');
                $sbc = bcadd($sbc , $info['fd_fromNums'] , 10);
                Db::name('wallet_user')->where('fd_id' , $info['fd_userId'])->setField('fd_sbcNums' , $sbc);
            }
            Db::name('wallet_order')->where('fd_orderNo', $order_no)->update(['fd_status' => 5]);
            $ins_data['order_no'] = $order_no;
            $ins_data['wallet_id'] = $info['fd_walletId'];
            $ins_data['user_id'] = $info['fd_userId'];
            $ins_data['admin'] = $admin_id;
            $ins_data['num'] = $info['fd_fromNums'];
            $ins_data['status'] = 1;
            $ins_data['type'] = 'out';
            $ins_data['tip'] = $tip;
            $ins_data['created_time'] = date('Y-m-d H:i:s');
            Db::name('wallet_order_log')->insert($ins_data);

            $result = [
                'msg' => '操作成功',
                'status' => 200
            ];
            Db::commit();

        }
        catch (\Exception $e)
        {
            Db::rollback();
            $result = [
                'msg' => '操作失败',
                'status' => 201,
            ];
        }
        return json($result);
    }

    /*
     * excel下载
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
}
