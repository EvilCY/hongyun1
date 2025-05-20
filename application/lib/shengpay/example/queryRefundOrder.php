<?php

require_once "../lib/ShengPay.Client.php";
require_once "ShengPay.Config.php";

$config = new ShengPayConfig();

$request = new QryRefundOrderRequest();

$request->setMchId($config->getMchId());
$request->setOutRefundNo("test0001");

$result = ShengPayClient::execute($request, $config);

$result->getReturnCode();
$result->getValues();

echo json_encode($result->getValues(),320);