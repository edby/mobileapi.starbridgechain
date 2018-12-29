<?php
/**
 * Created by PhpStorm.
 * User: gh
 * Date: 2018/6/12
 * Time: 11:22
 */

namespace app\wallet\controller;


use think\Controller;
use think\Db;
use think\Request;
use app\wallet\service\Email;
class PublicController extends Controller
{

    public function getTransferLogList(Request $request)
    {
        ini_set('date.timezone','Asia/Shanghai');
        $wallet_adr = $request->param('address');
        $sign = $request->param('sign');
        $pageNumber = $request->param('pageNumber' , 1);
        $pageSize = $request->param('pageSize' , 10);
        $limit = ($pageNumber - 1) * $pageSize . ',' . $pageSize;
        $my_sign = sha1(md5($wallet_adr . 'wallet2018' . date('YmdH')));
        if($sign != $my_sign)
            return jorx(['code' => 201,'msg' => '非法操作!']);
        $user_id = Db::name('wallet_purse')->where('fd_walletAdr' , $wallet_adr)->value('fd_userId');
        $where['fd_user_id|fd_col_user_id'] = $user_id;
        $info = Db::name('wallet_transfer_log')
            ->where($where)
            ->order('created_time','desc')
            ->limit($limit)
            ->select();
        $count = Db::name('wallet_transfer_log')
            ->where($where)
            ->count();
        $data = [];
        foreach ($info as $key => $value)
        {
            $data[$key]['value'] = n_f(number_format( $value['fd_transfer_sbc']-0,10,'.',''));

            if($value['fd_user_id'] == $user_id && $value['fd_col_user_id'] != $user_id)//转出
            {
                $data[$key]['fd_type'] = '2';
            }
            else if ($value['fd_col_user_id'] == $user_id && $value['fd_user_id'] != $user_id)//转入
            {
                $data[$key]['fd_type'] = '1';
            }
            else if($value['fd_col_user_id'] == $user_id && $value['fd_user_id'] == $user_id)//余额整理
            {
                $data[$key]['fd_type'] = '余额整理';
            }
            $data[$key]['from'] = $value['fd_begin_walletAdr'];
            $data[$key]['to'] = $value['fd_end_walletAdr'];
            $data[$key]['hash'] = $value['order_no'];
            $data[$key]['time'] = $value['created_time'];
        }
        if(empty($info)){
            return jorx(['status' => 201,'msg' => '数据为空!','data' => [] , 'count' => 0]);
        }
        return jorx(['status' => 200,'msg' => '获取成功!','data' => $data , 'count' => $count]);
    }

    /**
     * 24小时快照定时任务
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function SynchronizeHour()
    {
        $where['fd_id']=['neq',734];
        $user_info = Db::name('wallet_user')->field('fd_id,fd_sbcNums,lock_sbcNums')->where($where)->select();

        $setting=Db::table('profit_setting_lock')->order('createTime desc')->limit(1)->select();
        $coefficient=Db::table('profit_syssetting')->value('coefficient');
        foreach ($user_info as $key => $value)
        {
            //快照额=余额+锁仓额*系数
            if($value['fd_sbcNums']<$setting[0]['sdtBase'])  //余额小于3000,只快照锁仓的,
            {
                $ins_data[] = [
                    'ph_userid' => $value['fd_id'],
                    'ph_sdt' => $value['lock_sbcNums']*$coefficient,
                    'ph_createTime' => date('Y-m-d H:i:s'),
                    'ph_hour' => date('G'),
                    'ph_days' => (int)date('d'),
                ];
            }else{                          //余额大于3000,快照锁仓+余额
                $sdt_num=bcadd($value['lock_sbcNums']*$coefficient,$value['fd_sbcNums'],15);
                $ins_data[] = [
                    'ph_userid' => $value['fd_id'],
                    'ph_sdt' => $sdt_num,
                    'ph_createTime' => date('Y-m-d H:i:s'),
                    'ph_hour' => date('G'),
                    'ph_days' => (int)date('d'),
                ];

            }

        }
        Db::name('profit_24hour')->insertAll($ins_data);
    }





}
