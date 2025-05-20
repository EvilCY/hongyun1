<!DOCTYPE HTML>
<html>
<head>
    <meta charset="utf-8">
    <title>测试查询退款订单接口</title>
    <style>
    </style>
</head>
<body>

<?php
$outRefundNo = empty($_POST["outRefundNo"]) ? "" : $_POST["outRefundNo"];
$mchId = empty($_POST["mchId"]) ? "" : $_POST["mchId"];
?>
<h2>查询退款订单</h2>
<form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">

    <table>
        <tbody>
        <tr>
            <td>outRefundNo:</td>
            <td><input type="text" name="outRefundNo" value="<?php echo $outRefundNo; ?>"></td>
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
    $request = new QryRefundOrderRequest();

    $request->setMchId($mchId);
    $request->setOutRefundNo($outRefundNo);
    $result = ShengPayClient::execute($request, $config);

    echo "<h2>服务返回结果:</h2>";
    echo "<textarea style='width: 1200px;height: 200px;'>" . htmlspecialchars(json_encode($result->getValues(), 320)) . "</textarea>";
}
?>
</body>
</html>