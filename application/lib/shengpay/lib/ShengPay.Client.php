<?php

require_once "ShengPay.Data.php";

class ShengPayClient
{
    /**
     * @param ShengpayRequest $request
     * @param ShengPayConfigInterface $config
     * @return ShengpayResult
     */
    public static function execute($request, $config)
    {
        $request->setNonceStr(self::getNonceStr());
        $request->setSign($config);
        $response = self::curl($request->getRequestUrl($config->isLive()), $request->getValues());
        return $response;
        // return ShengpayResult::Init($config, $response);
    }
    
    /**
     *
     * 通知结果处理
     * @param ShengPayConfigInterface $config
     * @param function $callback
     *
     */
    public static function notify($config, $callback, &$msg)
    {
        $json = file_get_contents("php://input");
        if (empty($json)) {
            return false;
        }

        try {
            $result = ShengpayResult::Init($config, $json);
        } catch (ShengPayException $e){
            $msg = $e->errorMessage();
            return false;
        }

        return call_user_func($callback, $result);
    }

    /**
     * 发起 server 请求
     * @param string $action
     * @param array $params
     * @return mixed
     */
    private static function curl($action, $params)
    {
        $data = json_encode($params, 320);
        // print_r($request);exit;
        $httpHeader = [];
        $ch = curl_init();
        $httpHeader[] = 'Content-Type:Application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_URL, $action);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeader);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //处理http证书问题
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $ret = curl_exec($ch);
        if (false === $ret) {
            $ret = curl_errno($ch);
        }
        curl_close($ch);
        return $ret;
    }

    /**
     *
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return 产生的随机字符串
     */
    public static function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

}