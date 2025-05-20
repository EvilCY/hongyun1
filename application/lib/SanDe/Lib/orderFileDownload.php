<?php
date_default_timezone_set('Asia/Shanghai');
header('Content-type:text/html;charset=utf-8');
// header('content-type:application/json;charset=utf-8');
require '../api/Common.php';
require '../api/PCCashier.php';

/**
 * 收款凭证下载接口
 */

function main($method='orderFileDownload')
{
    $params=
    [
        "orderCode" =>"20230727054505",
        "orderDate" =>"20230726"
    ];

    // 实例化客户端
    $client = new PCCashier;
    // 参数, 每次需要重新赋值  
    $client->body = $params;
    // 返回结果
    //$ret = $client->request($method);

    $url      = 'https://cashier.sandpay.com.cn/gw/api/orderfile/download';
    $postData = $client->postData('sandPay.orderFile.download');
    $ret    = $client->httpPost($url, $postData);

    $arr      = array();
    $response = urldecode($ret);
    $arrStr   = explode('&', $response);
    foreach ($arrStr as $str) {
        $p         = strpos($str, "=");
        $key       = substr($str, 0, $p);
        $value     = substr($str, $p + 1);
        $arr[$key] = $value;
    }
    $ret = $arr;



    $apiMap=$client->apiMap();
    // 验签 & 返回结果
    $verifyFlag = $client->verify($ret['data'], $ret['sign']);  

    $ret['data']=json_decode($ret['data']);
    $postData = $client->postData($apiMap[$method]['method']);
    $postData['data']=json_decode($postData['data']);
    return  json_encode([
    'verify'    => $verifyFlag==true?'验签成功!':'验签失败!',
        'jjson'    => json_encode($postData),
        'json'     =>  json_encode($ret,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ],JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

}

echo main();

