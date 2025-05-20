<?php
/**
 * Created by PhpStorm.
 * User: yabin
 * Date: 2021/9/10
 * Time: 11:08
 */
namespace JytPay;

include 'JytJsonClient.php';

header("Content-Type:text/html;   charset=utf-8");
ini_set("display_errors", "On");
error_reporting(E_ALL | E_STRICT);

$client = new JytJsonClient;
$client->init();

// 点击提交按钮后才执行
if (!empty($_POST['op1001_test'])) {
    // 报文头信息
    $data['head']['version']='2.0.0';
    $data['head']['tranType']='01';
    $data['head']['merchantId']= $client->config->merchant_id;
    $data['head']['tranTime']=date('YmdHis',time());
    $data['head']['tranFlowid']= $client->config->merchant_id . date('YmdHis',time()) . substr(rand(),4);
    $data['head']['tranCode']= 'OP1001';
    $data['head']['respCode']='';
    $data['head']['respCesc']='';
    // 报文体
    $data['body']['payChannel'] = $_POST['payChannel'];
    $data['body']['payMode'] = $_POST['payMode'];
    $data['body']['orderId'] = $_POST['orderId'];
    $data['body']['totalAmt'] = $_POST['totalAmt'];
    $data['body']['subject'] = $_POST['subject'];
    $data['body']['notifyUrl'] = $_POST['notifyUrl'];
    $data['body']['body'] = $_POST['body'];
    $data['body']['spbillCreatIp'] = $_POST['spbillCreatIp'];
    if (!empty($_POST['splitFlag']) && !empty($_POST['splitAmt'])) {
        $data['body']['splitAmt'] = $_POST['splitAmt'];
        $data['body']['splitFlag'] = $_POST['splitFlag'];
    }
    $res = $client->sendReq($data);
    echo $res;
}