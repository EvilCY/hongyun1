<!DOCTYPE HTML>
<html>
<head>
    <meta charset="utf-8">
    <title>测试预收单接口</title>
    <style>
    </style>
</head>
<body>

<?php
// 定义变量并默认设置为空值

$attach = empty($_POST["attach"]) ? "" : $_POST["attach"];
$body = empty($_POST["body"]) ? "" : $_POST["body"];
$clientIp = empty($_POST["clientIp"]) ? "" : $_POST["clientIp"];
$currency = empty($_POST["currency"]) ? "" : $_POST["currency"];
$detail = empty($_POST["detail"]) ? "" : $_POST["detail"];
$extra = empty($_POST["extra"]) ? "" : $_POST["extra"];
$isNeedShare = empty($_POST["isNeedShare"]) ? "" : $_POST["isNeedShare"];
$mchId = empty($_POST["mchId"]) ? "" : $_POST["mchId"];
$mchMemberInfo = empty($_POST["mchMemberInfo"]) ? "" : $_POST["mchMemberInfo"];
$notifyUrl = empty($_POST["notifyUrl"]) ? "" : $_POST["notifyUrl"];
$outTradeNo = empty($_POST["outTradeNo"]) ? "" : $_POST["outTradeNo"];
$sdpAppId = empty($_POST["sdpAppId"]) ? "" : $_POST["sdpAppId"];
$subMchId = empty($_POST["subMchId"]) ? "" : $_POST["subMchId"];
$timeExpire = empty($_POST["timeExpire"]) ? "" : $_POST["timeExpire"];
$totalFee = empty($_POST["totalFee"]) ? "" : $_POST["totalFee"];
$tradeType = empty($_POST["tradeType"]) ? "" : $_POST["tradeType"];
?>
<h2>预收单</h2>
<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">

    <table>
        <tbody>
        <tr>
            <td>outTradeNo:</td>
            <td><input type="text" name="outTradeNo" value="<?php echo $outTradeNo; ?>"></td>
        </tr>
        <tr>
            <td>tradeType:</td>
            <td><input type="text" name="tradeType" value="<?php echo $tradeType; ?>"></td>
        </tr>
        <tr>
            <td>sdpAppId:</td>
            <td><input type="text" name="sdpAppId" value="<?php echo $sdpAppId; ?>"></td>
        </tr>
        <tr>
            <td>mchId:</td>
            <td><input type="text" name="mchId" value="<?php echo $mchId; ?>"></td>
        </tr>
        <tr>
            <td>totalFee:</td>
            <td><input type="text" name="totalFee" value="<?php echo $totalFee; ?>"></td>
        </tr>
        <tr>
            <td>timeExpire:</td>
            <td><input type="text" name="timeExpire" value="<?php echo $timeExpire; ?>"></td>
        </tr>
        <tr>
            <td>subMchId:</td>
            <td><input type="text" name="subMchId" value="<?php echo $subMchId; ?>"></td>
        </tr>
        <tr>
            <td>notifyUrl:</td>
            <td><input type="text" name="notifyUrl" value="<?php echo $notifyUrl; ?>"></td>
        </tr>
        <tr>
            <td>mchMemberInfo:</td>
            <td><input type="text" name="mchMemberInfo" value="<?php echo $mchMemberInfo; ?>"></td>
        </tr>
        <tr>
            <td>isNeedShare:</td>
            <td><input type="text" name="isNeedShare" value="<?php echo $isNeedShare; ?>"></td>
        </tr>
        <tr>
            <td>extra:</td>
            <td><input type="text" name="extra" value="<?php echo $extra; ?>"></td>
        </tr>
        <tr>
            <td>attach:</td>
            <td><input type="text" name="attach" value="<?php echo $mchId; ?>"></td>
        </tr>
        <tr>
            <td>detail:</td>
            <td><input type="text" name="detail" value="<?php echo $detail; ?>"></td>
        </tr>
        <tr>
            <td>body:</td>
            <td><input type="text" name="body" value="<?php echo $body; ?>"></td>
        </tr>
        <tr>
            <td>currency:</td>
            <td><input type="text" name="currency" value="<?php echo $currency; ?>"></td>
        </tr>
        <tr>
            <td>clientIp:</td>
            <td><input type="text" name="clientIp" value="<?php echo $clientIp; ?>"></td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: center"><input type="submit" name="submit" value="Submit"></td>
        </tr>
        </tbody>
    </table>
</form>

<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    require_once "../lib/ShengPay.Client.php";
    require_once "ShengPay.Config.php";
    $config = new ShengPayConfig();
    $request = new UnifiedOrderRequest();
    $request->setTradeType($tradeType);
    $request->setAttach(json_decode($attach, true));
    $request->setBody($body);
    $request->setCurrency($currency);
    $request->setDetail($detail);
    $request->setExtra(json_decode($extra, true));
    $request->setIsNeedShare($isNeedShare);
    $request->setNotifyUrl($notifyUrl);
    $request->setOutTradeNo($outTradeNo);
    $request->setTimeExpire($timeExpire);
    $request->setTotalFee($totalFee);
    $request->setClientIp($clientIp);
    $request->setSubMchId($subMchId);
    $request->setMchId($mchId);
    $request->setMchMemberInfo($mchMemberInfo);
    $request->setSdpAppId($sdpAppId);
    $result = ShengPayClient::execute($request, $config);
    print_r($result);exit;
    echo "<h2>服务返回结果:</h2>";
    echo "<textarea style='width: 1200px;height: 200px;'>" . htmlspecialchars(json_encode($result->getValues(), 320)) . "</textarea>";
}
?>
</body>
</html>