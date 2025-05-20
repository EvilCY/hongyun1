<!DOCTYPE html>
<html>
  <head>
    <script type="text/javascript" src="jquery.min.js"></script>
  </head>
  <style>
      .editor-label {
          margin: 0px 0 0 0;
          height: 33px;
          display: inline-block;
          width: 500px;
          min-width: 500px;
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
          width: 190px;
      }
  </style>
<body>
  <form action="" method="post" id="form" class="editor-label">
      <br>
      <label></label>
      <input type="text" name="chaxun" value="点击【下单短信】按钮发起测试" readonly="readonly"/>
      <br>
      <label>客户号：</label>
      <input type="text" name="custNo" value="80000111170" placeholder="客户号"/>
      <br>
      <label>姓名：</label>
      <input type="text" name="name" value="李四" placeholder="姓名"/>
      <br>
      <label>身份证号：</label>
      <input type="text" name="idCardNo" value="310115200203261110" placeholder="身份证"/>
      <br>
      <label>手机号：</label>
      <input type="text" name="mobile" value="13612622107" placeholder="手机号"/>
      <br>
      <label>银行卡号：</label>
      <input type="text" name="bankCardNo" value="6200601234567892227" placeholder="银行卡号"/>
      <br>
      <label>有效期：</label>
      <input type="text" name="expiredDate" value="" placeholder="银行卡有效期"/>
      <br>
      <label>CVV2：</label>
      <input type="text" name="cvv2" value="" placeholder="银行卡效验码"/>
      <br>
      <label>交易金额：</label>
      <input type="text" name="tranAmt" value="100" placeholder="交易金额"/>
      <br>
      <label>商户订单号：</label>
      <input type="text" name="orderId" value="PHP_D<?php echo mt_rand();?>" placeholder="商户订单号"/>
      <br>
      <label></label>
      <input type="button" name="button" value="下单短信" id="button"/>
  </form>

</body>
  <script>
  	$(function() {
    	$('#button').click(function() {
            let orderId = $("input[name='orderId']").val();
            let idCardNo = $("input[name='idCardNo']").val();//证件号
            let tranAmt = $("input[name='tranAmt']").val();//交易金额
            let custNo = $("input[name='custNo']").val();//客户号
            let mobile = $("input[name='mobile']").val();
            let bankCardNo = $("input[name='bankCardNo']").val();
            let name = $("input[name='name']").val();
        	$.ajax({
              type: 'post',
              url: 'td1004.php',
              data: $("#form").serialize(),
              dataType : 'json',
              success: function(data) {
                  if (data.body) {
                      if (data.body.tranState == '01') {
                          alert(data.body.remark);
                          setTimeout(function() {
                              window.location.href = 'html_td4005.php?orderId='+orderId+'&idCardNo='+idCardNo+'&tranAmt='+tranAmt+'&custNo='+custNo+'&mobile='+mobile+'&bankCardNo='+bankCardNo+'&name='+name;
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