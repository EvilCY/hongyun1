<?php

require_once "../lib/ShengPay.Client.php";
require_once "ShengPay.Config.php";

$msg = "OK";
$config = new ShengPayConfig();
$result = ShengPayClient::notify($config, function ($notifyResult) {
    error_log("receiveNotifyContent" . json_encode($notifyResult, 320) . "\r\n", 3, "../logs/notify." . date("YmdHis") . ".log");
    return true;
}, $msg);

if ($result) {
    echo "SUCCESS";
} else {
    echo "FAIL|" . $msg;
}