<!DOCTYPE html>
<html>
<head>
    <title>金运通主动扫码支付测试页面</title>
    <script type="text/javascript" src="jquery.min.js"></script>
</head>
<style>
    .editor-label {
        margin: 0px 0 0 0;
        height: 33px;
        display: inline-block;
        width: 600px;
        min-width: 600px;
        position: relative;
    }
    .editor-label label {
        width: 120px;
        font-size: 14px;
        line-height: 30px;
        display: inline-block;
        text-align: right;
    }
    .editor-label input[type='text'], .editor-label select, .editor-box input[type='text'], .editor-box select {
        height: 23px;
        border: 1px solid #ddd;
        padding-left: 10px;
        font-size: 13px;
        border-radius: 3px;
        width: 200px;
    }
</style>
<body>
<form action="" method="post" id="form" class="editor-label">
    <br>
    <label></label>
    <input type="text" name="op1001_test" value="点击【扫码支付】按钮发起测试" readonly="readonly"/>
    <br>
    <label>支付渠道：</label>
    <select name = "payChannel">
        <option value="01" selected="selected">支付宝</option>
        <option value="00">微信</option>
        <option value="02">QQ</option>
        <option value="05">银联二维码</option>
    </select>
    <br>
    <label>支付模式：</label>
    <input type="text" name="payMode" value="00" placeholder="支付模式：00动扫码" readonly="readonly"/></td>
    <br>
    <label>订单号：</label>
    <input type="text" name="orderId" value="P<?php echo date('YmdHis',time()); echo mt_rand(); ?>" placeholder="商户订单号"/>
    <br>
    <label>交易金额：</label>
    <input type="text" name="totalAmt" value="0.01" placeholder="交易金额"/>
    <br>
    <label>订单描述：</label>
    <input type="text" name="subject" value="Apple/苹果 iPhone 7 耳套" placeholder=""/>
    <br>
    <label>通知地址：</label>
    <input type="text" name="notifyUrl" value="http://192.168.50.12:8085/onepay-notifyService/notify/map/WXASUnion106.do" placeholder="接收回调的通知地址"/>
    <br>
    <label>支付描述：</label>
    <input type="text" name="body" value="Apple/苹果 iPhone 7 耳套" placeholder="请输入支付描述"/>
    <br>
    <label>支付IP：</label>
    <input type="text" name="spbillCreatIp" value="127.0.0.1" placeholder="请输入交易IP"/>
    <br>
    <label>分账金额：</label>
    <input type="text" name="splitAmt" value="" placeholder="请输入分账金额"/>
    <br>
    <label>分账标识：</label>
    <input type="text" name="splitFlag" value="" placeholder="01分账交易;00或空为普通交易"/>
    <br>
    <label></label>
    <input type="button" name="button" value="扫码支付" id="button"/>
</form>
</body>
<script>
    $(function() {
        $('#button').click(function() {
            $.ajax({
                type: 'post',
                url: 'op1001_as.php',
                data: $("#form").serialize(),
                dataType : 'json',
                success: function(data) {
                    if (data.body) {
                        if (data.body.tranState == '12') {
                            alert(data.head.respDesc);
                            setTimeout(function() {
                                // 打开新页面
                                window.open("html_codeImg.php?codeImgUrl="+ data.body.codeImgUrl);
                            }, 1000);
                        } else {
                            alert(data.head.respDesc);
                        }
                    } else {
                        alert(data.head.respDesc);
                    }
                }
            });
        });
    });
</script>
</html>