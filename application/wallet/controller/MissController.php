<?php

namespace app\wallet\controller;

class MissController extends BaseController
{

    /**
     * miss路由
     * @return \think\response\Json
     */
    public function miss()
    {
        return json(['code' => '400','msg' => '请求方式或地址错误!']);
    }
}