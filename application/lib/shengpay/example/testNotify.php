<!DOCTYPE HTML>
<html>
<head>
    <meta charset="utf-8">
    <title>测试通知接口</title>
    <script src="https://cdn.staticfile.org/jquery/1.10.2/jquery.min.js"></script>
    <style>
    </style>
</head>
<body>

<h2>通知</h2>
<form method="post" action="notify.php">
    <table>
        <tbody>
        <tr>
            <td>通知内容:</td>
            <td>
                <textarea id="content" name="content" style='width: 1200px;height: 200px;'></textarea>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="text-align: center"><input type="button" id="notify" name="submit" value="Submit"></td>
        </tr>
        </tbody>
    </table>
</form>
<script>
    $(document).ready(function(){
            $('#notify').on('click',function(){
                $.ajax({
                    url:"<?php echo dirname($_SERVER['PHP_SELF'])?>/notify.php",
                    type:"POST",
                    data:$('#content').val(),
                    contentType:"application/json",  //缺失会出现URL编码，无法转成json对象
                    success:function(result){
                        console.log(result);
                        alert(result);
                    }
                });
            });
    });
</script>
</body>
</html>