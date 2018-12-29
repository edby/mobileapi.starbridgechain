<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
//下载apk
function downloads($filename)
{
    $header = get_headers($filename, 1);
    $size = $header['Content-Length'];
    $showname = "download.apk";
    header("Content-type: text/plain");
    header("Accept-Ranges: bytes");
    header("Content-Disposition: attachment; filename=".$showname);
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0" );
    header("Pragma: public" );
    header("Accept-Length:".$size);
    header('Content-Length: ' . $size);
    readfile($filename);
}

/**
 * 密码盐值
 * @return string
 */
function salt(){
    return \think\helper\Str::random(6);
}

/**
 * accessToken
 * @return string
 */
function accessToken(){
    //c6d889d1-9810-4cc3-8ecc-8b2c3a40e4ab
    $accessToken = md5($_SERVER['REQUEST_TIME_FLOAT'] . uniqid('',true));
    $startNum    = 8;
    for($i = 0 ;$i < 4; ++$i){
        $accessToken = substr_replace($accessToken,'-',$startNum,0);
        $startNum   += 5;
    }
    return $accessToken;
}

/**
 * 根据请求参数返回xml或json
 * @param $data
 * @return \think\response\Json
 */
function jorx($data){
    $request         = request();
    $returnType      = $request->returnType;
    return $returnType($data);
}

/**
 * 格式化字段信息
 * @param $data    array 原始数据
 * @param $filter  array 过滤字段
 * @return array
 */
function format_field($data,$filter = []){
    $field_prefix         = 'fd_';//字段前缀
    $return_data          = [];
    foreach ($data as $key => $item){
        if(!in_array($key,$filter)){
            $return_data[str_replace($field_prefix,'',$key)] = $item;
        }
    }
    return $return_data;
}

/**
 * 将参数拼接为url: key=value&key=value
 * @param $data
 * @return string
 */
function array2urlParams( array $data ){
    $string = '';
    if(!empty($data)){
        $array = array();
        foreach( $data as $key => $value ){
            $array[] = $key.'='.$value;
        }
        $string = implode("&",$array);
    }
    return $string;
}

/**
 * 生成钱包唯一地址
 * @return string
 */
function createPurse(){
    mt_srand( (double) microtime() * 10000);
    $str = $_SERVER['REQUEST_TIME_FLOAT'] . uniqid(mt_rand(1, 999999));
    return strtolower('0x' .  md5($str) . \think\helper\Str::random(8));
}



/**
 * 生成唯一订单号
 * @return string
 */
function createOrderNo(){
    //随机数发生器种子
    mt_srand( (double) microtime() * 10000);
    //订单号
    return  date('YmdHis') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * 格式化数字
 * @param $num
 * @return string
 */
function n_f($num){
    return rtrim(rtrim($num,'0'),'.');
}