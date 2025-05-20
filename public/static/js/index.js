function init(data) {
	$.each(data, function(i, item){  
		var x = randomNum(200, 980) / 100,
			  y = randomNum(200, 550) / 100;
		$("<img/>", { "id": "img" + item.id, "alt": item.money, "class": "profit_icon" ,"src": "/static/img/pin1.png" }).appendTo(".index-main").click(function() {
			$.get('/get_profit?id='+item.id, function(result){
				if(result.code) {
					$("#img"+item.id).fadeToggle();
					$('#sum').html(parseInt($("#sum").html()) + parseInt(result.data));
          $('#sum2').html(parseInt($("#sum2").html()) + parseInt(result.data));
          $("#msg").html('+'+result.data).css({ "top": x + "rem", "left": y + "rem" }).show().animate({top:'2rem'},'slow').fadeOut()
				} else {
          if(result.url == "/restart") {
            // 扩展API加载完毕后调用onPlusReady回调函数
            document.addEventListener( 'plusready', onPlusReady, false );
            // 扩展API加载完毕，现在可以正常调用扩展API
            function onPlusReady() {
            }
            // 重启当前的应用
            if(window.plus) {
              plus.runtime.restart();
            } else {
              alert(result.msg);
              top.location.href = '/login';
            }
          }
          $("#msg").html(result.msg).css({ "top": x + "rem", "left": y + "rem", "font-size":"0.2rem"}).show().animate({top:(parseInt($("#img"+item.id).css('top')) + 40) + 'px'}).fadeOut()
        }
			});
		}).css({ "top": x + "rem", "left": y + "rem" ,"opacity": (1 - (parseInt(item.create_time)*1000 - (new Date()).valueOf())/86400000)}).fadeTo( parseInt(item.create_time)*1000 - (new Date()).valueOf() ,1);
  }); 

};

function randomNum(lower, upper) {
	return Math.floor(Math.random() * (upper - lower)) + lower;
}

// 复制邀请码
$(".copy-code").click(function() {
	var clipboard = new Clipboard('.copy-code');
	clipboard.on('success', function(e) {
		$(".copy-code").html("复制成功");

		e.clearSelection();
		console.log(e.clearSelection);
	});
})

// 发送验证码
$(".v-code-btn").click(function(){
	$.get('/sms?mobile='+$("#mobile").val(), function(result){
		alert(result.msg);
	});
});

// 检测是否有推广权限
$("#ajax-get-invite").click(function(){
  $.get('/invite', function(result){
    if(result.code==0) {
      $(".index-main .pop-box").fadeIn();
    } else {
      top.location.href = '/invite';
    }
  });
});

// 我的团队检测
$("#ajax-get-team").click(function(){
  $.get('/invite', function(result){
    if(result.code==0) {
      $(".index-main .pop-box").fadeIn();
    } else {
      top.location.href = '/team';
    }
  });
});

$('form').ajaxForm(function(data) {
	alert(data.msg);
	if(data.code) {
		top.location.href = data.url;
	}
});

// 矿机购买弹框
$(".buy-machine .buy-btn").click(function() {
  $.get($(this).attr('data-url'), function(result){
  	if(result.code == 1) {
  		$("#buy_id").val(result.msg);
  		$(".buy-machine .pop-box").show();
  	} else {
  		$(".buy-machine .pop-box").show();
  		$(".buy-machine .pop-box .msg").hide();
  		$("#errormsg").html(result.msg);
      $(".buy-machine .pop-box .default").show();
      if (result.url) {
        setTimeout(function() { window.location.href = result.url; }, 1000);
      } else {
      	setTimeout(function() { window.location.reload(); }, 3000);
      }
  	}
  });
})

// 取消购买
$(".buy-machine .cancle").click(function() {
  $("#buy_id").val('');
  $(".buy-machine .pop-box").hide();
})

// 确认购买
$(".buy-machine .yes").click(function() {
  $(".buy-machine .pop-box .msg").hide();
  var pw = $("#pay_password").val(),
  		buy_id = $("#buy_id").val();
  if (pw && buy_id) {
  	$.post("/buy", {id: buy_id, pay_password: pw, number: 1 },function(data){
        if(data.code == 1) {
        	$(".buy-machine .pop-box .success").show();
        } else {
        	$("#errormsg").html(data.msg);
        	$(".buy-machine .pop-box .default").show();
        	if(data.url) {
            if(data.url == "/restart") {
              // 扩展API加载完毕后调用onPlusReady回调函数
              document.addEventListener( 'plusready', onPlusReady, false );
              // 扩展API加载完毕，现在可以正常调用扩展API
              function onPlusReady() {
              }
              // 重启当前的应用
              if(window.plus) {
                plus.runtime.restart();
              } else {
                alert(data.msg);
                top.location.href = '/login';
              }
            } else {
              setTimeout(function() { window.location.href = data.url; }, 1000);
            }
		      } else {
		      	setTimeout(function() { window.location.reload(); }, 3000);
		      }
        }
        if(data.url) {
        	setTimeout(function() { window.location.href = data.url; }, 1000);
        }
    }); 
  } else {
    $(".buy-machine .pop-box .default").show();
    setTimeout(function() { window.location.reload(); }, 3000);
  }
});

// H5 plus事件处理
// 状态栏 和 下拉刷新
function plusReady(){
  // 获取系统状态栏高度
  var lh = plus.navigator.getStatusbarHeight();
  var st = plus.navigator.isImmersedStatusbar();
  var ws = plus.webview.currentWebview();
  if(st) {
  	$("header").css("padding-top",lh+"px");
    $(".prize-pool").css('padding-top',lh+"px");
  } else {
  	$("header").css("padding-top","0px");
  }
	ws.setPullToRefresh({support:true,style:'circle',offset:'45px'}, onRefresh);
}
if(window.plus){
	plusReady();
}else{
	document.addEventListener('plusready', plusReady, false);
}

function onRefresh(){
	setTimeout(function(){
		window.location.reload();
	},1000);
}

// 持有矿机
$('.team .team-item .right .btn').click(function() {
  $('#pop-box'+$(this).attr('data-id')).fadeIn();
});
$('.team .pop-box .close').click(function() {
  $('.team .pop-box').hide();
});


var tm1;
// 倒计时
function timer(intDiff) {
  intDiff = parseInt(intDiff); //倒计时总秒数
  tm1 = setInterval(function() {
    var day = 0,
      hour = 0,
      minute = 0,
      second = 0; //时间默认值
    if (intDiff > 0) {
      hour = Math.floor(intDiff / (60 * 60));
      minute = Math.floor(intDiff / 60) - day * 24 * 60 - hour * 60;
      second = Math.floor(intDiff) - day * 24 * 60 * 60 - hour * 60 * 60 - minute * 60;
    }
    if (hour <= 9) hour = '0' + hour;
    if (minute <= 9) minute = '0' + minute;
    if (second <= 9) second = '0' + second;
    hourArr = timeFormat(hour);
    minuteArr = timeFormat(minute);
    secondArr = timeFormat(second);
    $('.prize-pool .count-main span:nth-child(1)').html(hourArr[0]);
    $('.prize-pool .count-main span:nth-child(2)').html(hourArr[1]);
    $('.prize-pool .count-main span:nth-child(4)').html(minuteArr[0]);
    $('.prize-pool .count-main span:nth-child(5)').html(minuteArr[1]);
    $('.prize-pool .count-main span:nth-child(7)').html(secondArr[0]);
    $('.prize-pool .count-main span:nth-child(8)').html(secondArr[1]);
    intDiff--;
  }, 1000);
}

function timeFormat(time) {
  return time.toString().split('');
}

$('.index-main .index-cancle').click(function() {
  $('.index-main .pop-box').hide();
});