<?php

require_once "../lib/ShengPay.Client.php";
require_once "ShengPay.Config.php";

$config = new ShengPayConfig();

$request = new RefundRequest();

$request->setMchId($config->getMchId());
$request->setOutRefundNo("sdkphpr1234567890" . date("YmdHis"));
$request->setOutTradeNo("IM37rR43bnB9tYs");
$request->setNotifyUrl("notifyUrl");
$request->setRefundDesc("test");
$request->setRefundFee("1");

$result = ShengPayClient::execute($request, $config);

$result->getReturnCode();
$result->getValues();

echo json_encode($result->getValues(),320);