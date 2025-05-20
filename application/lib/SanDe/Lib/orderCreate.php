<?php
date_default_timezone_set('Asia/Shanghai');
header('Content-type:text/html;charset=utf-8');
// header('content-type:application/json;charset=utf-8');
require '../api/Common.php';
require '../api/PCCashier.php';

/**
 * 统一下单接口
 */

 function main($method='orderCreate')
 {
     $params=
     [
         "userId" => "123",
         "orderCode" => "20230726161903",
         "orderTime" => "20230726161500",
         "totalAmount" => "000000000003",
         "subject" => "话费充值",
         "body" => "用户购买话费0.12",
         "currencyCode" => "156",
         "notifyUrl" => "http://ylui.vegclubs.com/",
         "frontUrl" => "http://ylui.vegclubs.com/",
         "txnTimeOut" => "",
         "extend" => ""
       ];
 
     // 实例化客户端
     $client = new PCCashier;
     // 参数
     $client->body = $params ;
     $apiMap=$client->apiMap();
     $form = '';
     $postData = $client->postData($apiMap[$method]['method']);
     $url      = 'https://cashier.sandpay.com.cn' . $apiMap[$method]['url'];
 
     $form = '<form action="' . $url . '" method="post">';
     foreach ($postData as $k => $v) {
         $form .= "{$k} <p><input type='text' name='{$k}' value='{$v}'></p>";
     }
     $form .= '<input type="submit" value="提交"></form>';
 
     echo $form;
 
     $postData['data']=json_decode($postData['data']);
     return  json_encode([
         'form'      => htmlentities($form),
         'verify'    => '',
         'jjson'    => json_encode($postData),
         'json'     =>  '',
         'url'      => 'https://open.sandpay.com.cn/product/detail/43306/43682/',
     ],JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
 }

echo main();

