<?php
date_default_timezone_set('Asia/Shanghai');
header('Content-type:text/html;charset=utf-8');
// header('content-type:application/json;charset=utf-8');
require '../api/Common.php';
require '../api/PCCashier.php';

/**
 * 退货申请接口
 */

function main($method='orderRefund')
{
    $params=
    [
        "orderCode" => "20230726144537",
        "oriOrderCode" => "2017091551421977",
        "refundAmount" => "000000000101",
        "notifyUrl" => "http://ylui.vegclubs.com/",
        "refundReason" => "test测试",
        "refundMarketAmount" => "",
        "extend" => ""
    ];

    // 实例化客户端
    $client = new PCCashier;
    // 参数, 每次需要重新赋值  
    $client->body = $params;
    // 返回结果
    $ret = $client->request($method);
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

