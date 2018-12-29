<?php
/**
 * Created by PhpStorm.
 * User: gaoshichong
 * Date: 2018/1/29
 * Time: 16:26
 */

namespace curl;


use think\Exception;

class Curl
{
    /**
     * get方式获取远程api/html
     * @param $url
     * @param array $request_header
     * @return mixed|string
     * @throws Exception
     */
    public static function get($url,array $request_header = [])
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        //gzip压缩
        curl_setopt($ch, CURLOPT_ENCODING, "");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //header头
        if(!empty($request_header)){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $request_header);
        }
        $result       = curl_exec($ch);
        $error        = curl_errno($ch);
        $error_msg    = curl_error($ch);
        curl_close($ch);
        if(0 !== $error){
            throw new Exception($error_msg);
        }
        $result = mb_convert_encoding($result, 'utf-8', 'gb2312,gbk,utf-8');
        return $result;
    }

    /**
     * post方式发送
     * @param $url
     * @param $data
     * @param array $request_header
     * @return mixed
     */
    public static function post($url,$data,array $request_header = [])
    {
        $curl = curl_init();
        if( count($request_header) >= 1 ){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $request_header);
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        $error  = curl_errno($curl);
        curl_close($curl);
        if(0 !== $error){
            return false;
        }
        return $output;
    }
}