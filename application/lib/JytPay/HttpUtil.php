<?php
namespace app\lib\JytPay;

class Http {
    /**
     * post请求
     * @param string $url 请求地址
     * @param array $param 参数
     * @return string ret 响应结果
     */
    function post($url, $param = array()) {
        if(!is_array($param)){
            throw new Exception("参数必须为array");
        }
        $http_post = curl_init($url);
        curl_setopt($http_post, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($http_post, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($http_post, CURLOPT_RETURNTRANSFER, 1); // 以流形式返回, 0 则直接打印
        curl_setopt($http_post, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)");
        curl_setopt($http_post, CURLOPT_POST, 1);// 设置为POST方式
        curl_setopt($http_post, CURLOPT_POSTFIELDS, $param);
        curl_setopt($http_post, CURLOPT_HEADER,false);// 不打印响应的头信息
        $rst = curl_exec($http_post);
        curl_close($http_post);

        return $rst;
    }
}