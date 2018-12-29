<?php
namespace app\wallet\controller;


use think\Db;
use think\Request;

class AdController
{
    /**
     * @param Request $request
     * @return \think\response\Json
     * 获取最新一条广告
     */
    public function getAd(Request $request)
    {
        if ($request->isPost()){
            //参数
            $client_type = $request->param('client_type','');//设备 安卓or苹果
            if ($client_type == null){
                return json(['code'=>401,'msg'=>'参数错误!']);
            }
            //获取有效期中的最新的一条数据
            $time = time();
            $res = Db::table('star_bridge_ad')
                ->field('id,ad_name,content_url,ad_url,image_url,image_url2,show_time,show_interval')
                ->where('start_time','<',$time)
                ->where('end_time','>',$time)
                ->where('status','=','1')
                ->order('id','DESC')
                ->find();
            if ($res){
                $data = [
                    'ad_id'         => $res['id'],
                    'user_id'       => $request->param('user_id',''),
                    'client_type'   => $client_type,
                    'record_type'   => 1,//获取类型
                    'create_time'   => time(),
                ];
                //保存入库
                Db::table('star_bridge_ad_record')->insert($data);
                //拼接的域名
                $url = $res['ad_url']."/";
                //优化返回数组
                $result = [
                    'id'                => $res['id'],
                    'ad_name'           => $res['ad_name'],
                    'content_url'       => $res['content_url'],
                    'image_url'         => $url.$res['image_url'],
                    'image_url2'        => $url.$res['image_url2'],
                    'show_time'         => $res['show_time'],
                    'show_interval'     => $res['show_interval'],
                ];
                return json(['code'=>200,'msg'=>'success!','data'=>[$result]]);
            }else{
                return json(['code'=>401,'msg'=>'没有有效的广告!']);
            }

        }else{
            return json(['code'=>401,'msg'=>'请求方式错误!']);
        }
    }

    /**
     * @param Request $request
     * @return \think\response\Json
     * 点击广告  保存点击信息
     */
    public function clickAd(Request $request)
    {
        if ($request->isPost()){
            $ad_id = $request->param('ad_id','');//广告ID
            $client_type = $request->param('client_type','');//设备 安卓or苹果
            if ($ad_id != '' && $client_type != ''){
                $data = [
                    'ad_id'         => $ad_id,
                    'user_id'       => $request->param('user_id',''),
                    'client_type'   => $client_type,
                    'record_type'   => 2,//点击类型
                    'create_time'   => time(),
                ];
                //保存点击信息
                Db::table('star_bridge_ad_record')->insert($data);

                //返回结果
                return json(['code'=>200,'msg'=>'success!']);
            }else{
                return json(['code'=>401,'msg'=>'参数错误!']);
            }
        }else{
            return json(['code'=>401,'msg'=>'请求方式错误!']);
        }
    }

}