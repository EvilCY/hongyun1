<!DOCTYPE HTML>
<html>
<head>
    <meta charset="utf-8">
    <title>测试退款接口</title>
    <style>
    </style>
</head>
<body>

<?php

$refundDesc = empty($_POST["refundDesc"]) ? "" : $_POST["refundDesc"];
$mchId = empty($_POST["mchId"]) ? "" : $_POST["mchId"];
$notifyUrl = empty($_POST["notifyUrl"]) ? "" : $_POST["notifyUrl"];
$outRefundNo = empty($_POST["outRefundNo"]) ? "" : $_POST["outRefundNo"];
$outTradeNo = empty($_POST["outTradeNo"]) ? "" : $_POST["outTradeNo"];
$refundFee = empty($_POST["refundFee"]) ? "" : $_POST["refundFee"];
?>
<h2>退款</h2>
<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">

    <table>
        <tbody>
        <tr>
            <td>outRefundNo:</td>
            <td><input type="text" name="outRefundNo" value="<?php echo $outRefundNo; ?>"></td>
        </tr>
        <tr>
            <td>outTradeNo:</td>
            <td><input type="text" name="outTradeNo" value="<?php echo $outTradeNo; ?>"></td>
        </tr>
        <tr>
            <td>mchId:</td>
            <td><input type="text" name="mchId" value="<?php echo $mchId; ?>"></td>
        </tr>
        <tr>
            <td>refundFee:</td>
            <td><input type="text" name="refundFee" value="<?php echo $refundFee; ?>"></td>
        </tr>
        <tr>
            <td>refundDesc:</td>
            <td><input type="text" name="refundDesc" value="<?php echo $refundDesc; ?>"></td>
        </tr>
        <tr>
            <td>notifyUrl:</td>
            <td><input type="text" name="notifyUrl" value="<?php echo $notifyUrl; ?>"></td>
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
    $request = new RefundRequest();
    $request->setNotifyUrl($notifyUrl);
    $request->setOutRefundNo($outRefundNo);
    $request->setRefundFee($refundFee);
    $request->setOutTradeNo($outTradeNo);
    $request->setMchId($mchId);
    $request->setRefundDesc($refundDesc);

    $result = ShengPayClient::execute($request, $config);

    echo "<h2>服务返回结果:</h2>";
    echo "<textarea style='width: 1200px;height: 200px;'>" . htmlspecialchars(json_encode($result->getValues(), 320)) . "</textarea>";
}
?>
</body>
</html>