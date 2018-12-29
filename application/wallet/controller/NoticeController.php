<?php

namespace app\wallet\controller;

use app\wallet\model\Notice;
use app\wallet\model\User;
use app\wallet\model\WalletRewardHandOutLogModel;
use think\Controller;
use think\Request;

class NoticeController extends Controller
{
    /**
     * 显示公告列表
     *
     * @return \think\Response
     */
    public function index()
    {
        $category = 1;
        $list = Notice::where('category',$category)
            ->select();
        if (!$list){
            $result = config('code.success');
            return json_encode($result);
        }

        $result = config('code.success');
//        var_dump($list,$result);die;

        $result['list'] = $list;
        return json_encode($result);
    }

    /**
     * 显示帮助中心
     */
    public function help()
    {
        $category = 2;
        $list = Notice::where('category',$category)
            ->select();
        if (!$list){
            $result = config('code.success');
            return json_encode($result);
        }

        $result = config('code.success');

        $result['list'] = $list;
        return json_encode($result);
    }

    /**
     * 显示常见问题
     */
    public function question()
    {
        $category = 3;
        $list = Notice::where('category',$category)
            ->select();
        if (!$list){
            $result = config('code.success');
            return json_encode($result);
        }

        $result = config('code.success');

        $result['list'] = $list;
        return json_encode($result);
    }

    /**
     * @param Request $request
     * @return \think\response\Json
     * 前台首页 置顶公告
     */
    public function firstNotice(Request $request)
    {
        if ($request->method() == 'POST'){
            $is_english = input('is_english');
            $top = db('star_bridge_notice')
                ->field('title,content,if(createDate="","",FROM_UNIXTIME(createDate,"%Y-%m-%d")) as createDate')
                ->where(['category'=>1,'top'=>1,'is_english'=>$is_english])
                ->find();
            return json(['code'=>200,'data'=>$top]);
        }
    }

    /**
     * @return \think\response\Json
     * 公告列表
     */
    public function listNotice()
    {
        $page = input('page');
        $size = input('size') ? input('size') : 5;
        $is_english = input('is_english') ? input('is_english') : 1;//默认为中文
        if ($page <= 1){
            $page = 1;
        }
        $start = ($page - 1)*$size;

        //公告
        $datas1 = db('star_bridge_notice')
            ->field('id,title,content,top,FROM_UNIXTIME(createDate,"%Y-%m-%d") as createtime,enclosure,enclosure_name')
            ->where(['category'=>1,'is_english'=>$is_english])
            ->limit($start,$size)
            ->order('top asc,id desc')
            ->select();

        //帮助中心
        $datas2 = db('star_bridge_notice')
            ->field('id,title,content,top,FROM_UNIXTIME(createDate,"%Y-%m-%d") as createtime,enclosure,enclosure_name')
            ->where(['category'=>2,'is_english'=>$is_english])
            ->limit($start,$size)
            ->order('top asc,id desc')
            ->select();

        //常见问题
        $datas3 = db('star_bridge_notice')
            ->field('id,title,content,top,FROM_UNIXTIME(createDate,"%Y-%m-%d") as createtime,enclosure,enclosure_name')
            ->where(['category'=>3,'is_english'=>$is_english])
            ->limit($start,$size)
            ->order('top asc,id desc')
            ->select();

        return json(['notice'=>$datas1,'help'=>$datas2,'question'=>$datas3]);
    }






    /**
     * 手动生成 之前数据的 推广码!
     */
    public function buildSpreadCode()
    {
        $userS = User::where('fd_spreadCode',null)->select();
        foreach ($userS as $row){
            if ($row->fd_spreadCode == null)
                $row->where('fd_id',$row->fd_id)->update([
                    'fd_spreadCode'   => spread_code(8),
                ]);
        }
    }

    public function spreadInfo(Request $request)
    {
        $user = $request->user;
        $pageNumber = $request->param('pageNumber' , 1);
        $pageSize = $request->param('pageSize' , 4);
        $limit = ($pageNumber - 1) * $pageSize . ',' . $pageSize;
        //返回当前登录用户的 推广码
        $fd_spreadCode = $user['fd_spreadCode'];

        //获取当前登录人的推广人列表
        $spreadList = User::where('fd_invitationCode',$fd_spreadCode)
            ->field('fd_email,fd_spreadCode')
            ->where('fd_logTimes','>',0)
            ->limit($limit)
            ->select();
        $result = config('code.success');

        $result['spreadCode'] = $fd_spreadCode;
        $result['spreadList'] = $spreadList;

        return json_encode($result);
    }

    public function spreadInfoSec(Request $request)
    {
        $fd_spreadCode = $request->param('fd_spreadCode');

        //获取二级推广人列表
        $spreadList = User::where('fd_invitationCode',$fd_spreadCode)
            ->field('fd_email')
            ->where('fd_logTimes','>',0)
            ->select();
        $result = config('code.success');

        $result['data'] = $spreadList;

        return json_encode($result);
    }

    public function rankingInfo()
    {
        WalletRewardHandOutLogModel::select();
    }

}
