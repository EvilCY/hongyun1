<!DOCTYPE HTML>
<html>
<head>
    <meta charset="utf-8">
    <title>测试订单查询接口</title>
    <style>
    </style>
</head>
<body>

<?php
$mchId = empty($_POST["mchId"]) ? "" : $_POST["mchId"];
$outTradeNo = empty($_POST["outTradeNo"]) ? "" : $_POST["outTradeNo"];
$signType = empty($_POST["signType"]) ? "" : $_POST["signType"];

?>
<h2>订单查询</h2>
<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">

    <table>
        <tbody>
        <tr>
            <td>outTradeNo:</td>
            <td><input type="text" name="outTradeNo" value="<?php echo $outTradeNo; ?>"></td>
        </tr>
        <tr>
            <td>mchId:</td>
            <td><input type="text" name="mchId" value="<?php echo $mchId; ?>"></td>
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
    $request = new QryOrderRequest();
    $request->setOutTradeNo($outTradeNo);
    $request->setMchId($mchId);
    $result = ShengPayClient::execute($request, $config);

    echo "<h2>服务返回结果:</h2>";
    echo "<textarea style='width: 1200px;height: 200px;'>" . htmlspecialchars(json_encode($result->getValues(), 320)) . "</textarea>";
}
?>
</body>
</html>