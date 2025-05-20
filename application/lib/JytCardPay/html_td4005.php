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
      <input type="text" name="chaxun" value="点击【验证支付】按钮发起测试" readonly="readonly" />
      <br>
      <label>客户号：</label>
      <input type="text" name="custNo" value="<?php echo $_GET['custNo'];?>" placeholder="" readonly="readonly" />
      <br>
      <label>姓名：</label>
      <input type="text" name="name" value="<?php echo $_GET['name'];?>" placeholder="姓名"  readonly="readonly" />
      <br>
      <label>身份证号：</label>
      <input type="text" name="idCardNo" value="<?php echo $_GET['idCardNo'];?>" placeholder="身份证"  readonly="readonly" />
      <br>
      <label>手机号：</label>
      <input type="text" name="mobile" value="<?php echo $_GET['mobile'];?>" placeholder="手机号"  readonly="readonly" />
      <br>
      <label>银行卡号：</label>
      <input type="text" name="bankCardNo" value="<?php echo $_GET['bankCardNo'];?>" placeholder="银行卡号"  readonly="readonly" />
      <br>
      <label>交易金额：</label>
      <input type="text" name="tranAmt" value="<?php echo $_GET['tranAmt'];?>" placeholder="交易金额"  readonly="readonly" />
      <br>
      <label>验证码：</label>
      <input type="text" name="verifyCode" value="" placeholder="手机验证码"  />
      <br>
      <label>商户订单号：</label>
      <input type="text" name="orderId" value="<?php echo $_GET['orderId']?>"  readonly="readonly" />
      <br>
      <label></label>
      <input type="button" name="button" value="验证支付" id="button"/>
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
              url: 'td4005.php',
              data: $("#form").serialize(),
              dataType : 'json',
              success: function(data) {
                  if (data.head.respCode == 'S0000000') {
                  	  alert(data.head.respDesc);
                      setTimeout(function() {
                    	  window.location.href = 'html_td4007.php?idCardNo='+idCardNo+'&tranAmt='+tranAmt+'&custNo='+custNo+'&mobile='+mobile+'&bankCardNo='+bankCardNo+'&name='+name;
                      }, 1000);
                  } else {
                      alert(data.head.respDesc);
                  }
              }
          });
        });
    });
  </script>
</html>