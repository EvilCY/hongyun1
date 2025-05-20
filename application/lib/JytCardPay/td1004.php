<?php
namespace JytPay\Client;

include 'JytJsonClient.php';

header("Content-Type:text/html;   charset=utf-8");
ini_set("display_errors", "On");
error_reporting(E_ALL | E_STRICT);

$client = new JytJsonClient;
$client->init();

// 点击提交按钮后才执行
if (!empty($_POST['chaxun'])) {
    // 报文头信息
    $data['head']['version']='1.0.1';
    $data['head']['tranType']='01';
    $data['head']['merchantId']= $client->config->merchant_id;
    $data['head']['tranDate']=date('Ymd',time());
    $data['head']['tranTime']=date('His',time());
    $data['head']['tranFlowid']= $client->config->merchant_id . date('YmdHis',time()) . substr(rand(),4);
    $data['head']['tranCode']= 'TD1004';
    $data['head']['respCode']='';
    $data['head']['respCesc']='';
    // 报文体
    $data['body']['custNo']= $_POST['custNo'];
    $data['body']['orderId']= $_POST['orderId']; //自动生成
    $data['body']['bankCardNo']= $_POST['bankCardNo']; //银行卡号
    $data['body']['idCardNo']= $_POST['idCardNo']; //身份证
    $data['body']['mobile']= $_POST['mobile']; //手机号
    $data['body']['name']=$_POST['name'];
    $data['body']['tranAmt']=$_POST['tranAmt'];
    if (!empty($_POST['expiredDate']) && !empty($_POST['cvv2'])) {
        $data['body']['expiredDate']=$_POST['expiredDate'];
        $data['body']['cvv2']= $_POST['cvv2'];
    }
    $res = $client->sendReq($data);
    echo $res;
}