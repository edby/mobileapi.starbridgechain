<?php

namespace app\wallet\controller;


use think\Request;

class UploadController extends BaseController
{
    /**
     * 文件上传
     * @param Request $request
     * @return \think\response\Json
     */
    public function upload(Request $request)
    {
        $img      = $request->file('img');
        if($img){
            //上传文件验证
            $validate = [
                'size'=>3145728, // 3M 3*1024*1024     3145728
                'ext'=>'jpg,png,gif'
            ];
            //保存路径
            $path = 'public/uploads/screenshot';
            //验证
            $info = $img->validate($validate)->move(ROOT_PATH . $path);
            if($info){
                $saveName = '/' . $path . '/' . $info->getSaveName();
                $saveName = str_replace('\\','/',$saveName);
                return jorx([
                    'code'  => 200,
                    'msg'   => '上传成功!',
                    'url'   => $request->root(true) . $saveName, //访问地址
                    'path'  => $saveName                          //相对web跟目录路径
                ]);
            }else{
                return jorx(['code' => 400,'msg' => '参数错误:' . $img->getError()]);
            }
        }else{
            return jorx(['code' => 400,'msg' => '参数错误:未获取到img文件参数!']);
        }
    }


    public function downloads(Request $request)
    {
        downloads('http://mobileapi.starbridgechain.com/download/'.$request->param('package'));
    }

    public function getSystemInfo()
    {
        $result['data'] = [
            'version' => '1.0.0.17',
            'url' => 'http://mobileapi.starbridgechain.com/wallet/upload/downloads/1.0.0.12.apk'
        ];
        $result['status'] = 200;
        $result['msg'] = '请求成功';
        return jorx($result);
    }


}