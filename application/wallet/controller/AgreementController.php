<?php
/**
 * Created by PhpStorm.
 * User: gaoshichong
 * Date: 2018/5/11
 * Time: 16:26
 */

namespace app\wallet\controller;


use app\wallet\model\Agreement;
use think\Request;

class AgreementController extends BaseController
{
    /**
     * 获取用户协议
     * @param Request $request
     * @param Agreement $agreement
     * @return \think\response\Json
     */
    public function getAgreement(Request $request,Agreement $agreement)
    {
        $type = $request->param('type' , '');
        if('' == $type){
            return jorx(['code' => 400,'msg' => '参数错误：type不能为空!']);
        }
        $map = ['agreement-chinese','agreement-english'];
        if(!in_array($type , $map)){
            return jorx(['code' => 400,'msg' => 'type类型错误，只能是agreement-chinese和agreement-english']);
        }
        $data = $agreement->where('fd_type',$type)->find();
        return jorx(['code' => 200,'msg' => '获取成功!','agreement' => $data['fd_text']]);
    }


    /**
     * 获取全部用户协议
     * @param Request $request
     * @param Agreement $agreement
     * @return \think\response\Json
     */
    public function getAgreementAll(Request $request, Agreement $agreement)
    {
        $agreement  = $agreement->field('fd_id as agreementId,fd_text as text,fd_type as type')->select();
        return jorx(['code' => 200,'msg' => '获取成功!','data' => $agreement]);
    }

    /**
     * 修改协议
     * @param Request $request
     * @param Agreement $agreement
     * @return \think\response\Json
     */
    public function editAgreement(Request $request,Agreement $agreement)
    {
        $agreementId = $request->param('agreementId',0) - 0;
        $text        = $request->param(false);
        $text         = isset($text['text']) ? $text['text'] : '';
        if(!$text){
            return jorx(['code' => 400,'msg' => '内容不能为空!']);
        }
        $agreement = $agreement->where('fd_id',$agreementId)->find();
        if(!$agreement){
            return jorx(['code' => 400,'msg' => '没有该协议!']);
        }
        $agreement->fd_text = $text;
        if(false !== $agreement->save()){
            return jorx(['code' => 200,'msg' => '修改成功!']);
        }
        return jorx(['code' => 400,'msg' => '修改失败，请稍后再试!']);
    }
}