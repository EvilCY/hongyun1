<?php

namespace App\Lib;

use app\index\controller\Payment;

class PayUtils
{

    private $arr;
    private $url;

    function filter($params) {
        $arr = array();
        foreach ($params as $key => $val) {
            if($key == "sign" || $key == "sign_type" || $val == "") {
                continue;
            }
            else {
                $arr[$key] = $params[$key];
            }
        }
        $this->arr = $arr;
        return $this;
    }

    function sort() {
        $arr = $this->arr;
        ksort($arr);
        reset($arr);
        $this->arr = $arr;
        return $this;
    }

    function buildUrl($urlEnCode = false) {
        $param = $this->arr;
        $arg  = "";
        foreach ($param as $key => $val) {
            $arg .= $key."=".($urlEnCode?urlencode($val):$val)."&";
        }
        $this->url = substr($arg,0,-1);
        return $this;
    }

    /**
     * md5加密签名
     * @param String $key 商户应用密钥（平台提供）
     * @return string
     */
    function sign(string $key) {
        $url = $this->url . $key;
        return md5($url);
    }


    /**
     * 验证签名
     * @param string $sign 签名结果
     * @param string $key 商户应用密钥（平台提供）
     * @return bool
     */
    function verifySign(string $sign, string $key) {
        $url = $this->url . $key;
        $newSign = md5($url);
        if($newSign == $sign) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * curl
     * @param $url
     * @param bool $post
     * @param int $timeout
     * @return bool|string
     */
    function getHttpResponse($url, $post = false, $timeout = 10){
    	$reserve = '3484';//预留信息必填 - 平台提供，如123456

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $httpheader[] = "Accept: */*";
        $httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
        $httpheader[] = "Connection: close";
        $httpheader[] = "reserve: ".$reserve;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if($post){
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}