<?php

require_once "../lib/ShengPay.Client.php";
require_once "ShengPay.Config.php";

$config = new ShengPayConfig();

$request = new UnifiedOrderRequest();
$request->setTradeType("alipay_app");
/*$request->setAttach([
    "other"=>"test"
]);*/
$request->setBody("华为P30");
$request->setCurrency("CNY");
$request->setDetail("华为P30");
/*$request->setExtra([
    "openid"=>"xxx",
    "other"=>"test"
]);*/
$request->setIsNeedShare("false");
$request->setNotifyUrl("http://www.baidu.com");
$request->setOutTradeNo("sdkphp1234567890" . date("YmdHis"));
$request->setTimeExpire(date("YmdHis", time() + 600));
$request->setTotalFee("100");
$request->setClientIp("192.168.1.100");
//$request->setSubMchId("111111");
$request->setMchId($config->getMchId());
$request->setMchMemberInfo("uinsjdlajsdl");
$request->setSdpAppId($config->getSdpAppId());

$result = ShengPayClient::execute($request, $config);

echo htmlspecialchars(json_encode($result->getValues(), 320));

