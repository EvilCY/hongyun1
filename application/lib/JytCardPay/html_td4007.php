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
      <input type="text" name="chaxun" value="点击【协议支付】按钮发起测试" readonly="readonly" />
      <br>
      <label>客户号</label>
      <input type="text" name="custNo" value="<?php echo $_GET['custNo'];?>" placeholder="" readonly="readonly" />
      <br>
      <label>银行卡号：</label>
      <input type="text" name="bankCardNo" value="<?php echo $_GET['bankCardNo'];?>" placeholder="银行卡号"  readonly="readonly" />
      <br>
      <label>交易金额：</label>
      <input type="text" name="tranAmt" value="<?php echo $_GET['tranAmt'];?>" placeholder="交易金额" />
      <br>
      <label></label>
      <input type="button" name="button" value="协议支付" id="button"/>
  </form>

</body>
  <script>
  	$(function() {
    	$('#button').click(function() {
        	$.ajax({
              type: 'post',
              url: 'td4007.php',
              data: $("#form").serialize(),
              dataType : 'json',
              success: function(data) {
                  if (data.head != null) {
                      alert(data.head.respDesc);
                  } else {
                      alert("交易异常，报文头为空");
                  }
              }
          });
        });
    });
  </script>
</html>