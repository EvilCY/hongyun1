<?php
date_default_timezone_set('Asia/Shanghai');
header('Content-type:text/html;charset=utf-8');
// header('content-type:application/json;charset=utf-8');
require './Common.php';
require './PCCashier.php';

class Test
{
    //统一下单接口
    public function orderCreate($params = null)
    {
        try{
            $params =  unserialize($_COOKIE['params']) ;
            if (!$params) throw new Exception('未获取入参，请保存入参后再进行测试');
        }catch(\Exception $e){
            exit('未获取入参，请保存入参后再进行测试');
        }
        // 实例化客户端
        $client = new PCCashier;
        // 参数
        $client->body = $params ;
        //生成表单
        $res = $client->form('orderCreate');
        $res['postData']['data']=json_decode($res['postData']['data']);

        return  json_encode([
            'params'=> json_encode($params),
            'jjson'=>json_encode([$res['postData']]),
            'form'=> '<fieldset style="width:15%;" class="myCode"><legend>统一下单接口</legend><pre>'.$res['form'].'</pre></fieldset>',
            'url' => 'https://open.sandpay.com.cn/product/detail/43301/43332/43333',
            'error'    => '0'
        ]);
    }

    // 退款申请
    public function orderRefund()
    {
        try{
            $params =  unserialize($_COOKIE['params']) ;
            if (!$params) throw new Exception('未获取入参，保存入参后再进行测试');
        }catch(\Exception $e){
            exit('未获取入参，保存入参后再进行测试');
        }
      // var_dump($params);
        // 实例化客户端
        $client = new PCCashier;
        // 参数, 每次需要重新赋值  

        $client->body = $params;

        // 返回结果
        $ret = $client->request('orderRefund');
        // 验签 & 返回结果
        $verifyFlag = $client->verify($ret['data'], $ret['sign']);
        $error=0;
        if (!$verifyFlag) {
            $error=1;
         } else {
             $ret['data']=json_decode($ret['data']);
             $postData = $client->postData('sandpay.trade.refund');
             $postData['data']=json_decode($postData['data']);
             return  json_encode([
                'verify'    => $verifyFlag==true?'验签成功!':'验签失败!',
                 'jjson'    => json_encode($postData),
                 'json'     =>  json_encode($ret),
                 'url'      => 'https://open.sandpay.com.cn/product/detail/43301/43332/43334',
                 'error'    => $error
             ]);
         }
    }

    // 订单查询
    public function orderQuery()
    {
        try{
            $params =  unserialize($_COOKIE['params']) ;
            if (!$params) throw new Exception('未获取入参，保存入参后再进行测试');
        }catch(\Exception $e){
            exit('未获取入参，保存入参后再进行测试');
        }
        // 实例化客户端
        $client = new PCCashier;
        // 参数, 每次需要重新赋值
        $client->body = $params;
        // 返回结果
        $ret = $client->request('orderQuery');
        // 验签 & 返回结果
        $verifyFlag = $client->verify($ret['data'], $ret['sign']);
        $error=0;
        if (!$verifyFlag) {
            $error=1;
         } else {
             $ret['data']=json_decode($ret['data']);
             $postData = $client->postData('sandpay.trade.query');
             $postData['data']=json_decode($postData['data']);
             return  json_encode([
                 'verify'    => $verifyFlag==true?'验签成功!':'验签失败!',
                 //'jjson'    => json_encode($postData),
                 'json'     =>  json_encode($ret),
                 'url'      => 'https://open.sandpay.com.cn/product/detail/43301/43332/43345',
                 'error'    => $error
             ]);
         }

    }

    // 异步通知通用接口
    public function notify()
    {
        // 实例化客户端
        $client = new PCCashier;

        $sign = $_POST['sign']; //签名
        $data = stripslashes($_POST['data']); //支付数据

        // 验签
        try {
            $verifyFlag = $client->verify($data, $sign);
            if (!$verifyFlag) throw new Exception('签名失败');
        } catch (\Exception $e) {
            exit('签名失败');
        }

        // 回调数据
        echo '<pre class="myCode">';
        print_r($data);
        echo '</pre>';
        exit;
    }

    //商户自主重发异步通知接口
    public function orderMcAutoNotice()
    {
        try{
            $params =  unserialize($_COOKIE['params']) ;
            if (!$params) throw new Exception('未获取入参，保存入参后再进行测试');
        }catch(\Exception $e){
            exit('未获取入参，保存入参后再进行测试');
        }
        // 实例化客户端
        $client = new PCCashier;
        // 参数, 每次需要重新赋值
        $client->body = $params;
        // 返回结果

        $ret = $client->request('orderMcAutoNotice');
        // 验签 & 返回结果
        $verifyFlag = $client->verify($ret['data'], $ret['sign']);
         $error=0;
         if (!$verifyFlag) {
            $error=1;
         } else {
             $ret['data']=json_decode($ret['data']);
             $postData = $client->postData('sandpay.trade.notify');
             $postData['data']=json_decode($postData['data']);
             return  json_encode([
                'verify'    => $verifyFlag==true?'验签成功!':'验签失败!',
                 'jjson'    => json_encode($postData),
                 'json'     =>  json_encode($ret),
                 'url'      => 'https://open.sandpay.com.cn/product/detail/43301/43332/44127',
                 'error'    => $error
             ]);
         }

    }
    //对账单申请接口
    public function clearfileDownload()
    {
        try{
            $params =  unserialize($_COOKIE['params']) ;
            if (!$params) throw new Exception('未获取入参，保存入参后再进行测试');
        }catch(\Exception $e){
            exit('未获取入参，保存入参后再进行测试');
        }
        // 实例化客户端
        $client = new PCCashier;
        // 参数
        $client->body = $params;
        // 返回值
        $ret = $client->request('clearfileDownload');
        // 验签
        $verifyFlag = $client->verify($ret['data'], $ret['sign']);
        $error=0;
        if (!$verifyFlag) {
            $error=1;
         } else {
             $ret['data']=json_decode($ret['data']);
             $postData = $client->postData('sandpay.trade.download');
             $postData['data']=json_decode($postData['data']);
             return  json_encode([
                'verify'    => $verifyFlag==true?'验签成功!':'验签失败!',
                 'jjson'    => json_encode($postData),
                 'json'     =>  json_encode($ret),
                 'url'      => 'https://open.sandpay.com.cn/product/detail/43301/43332/43348',
                 'error'    => $error
             ]);
         }
    }

    //交易退货异步通知接口
    public function BackGoodsNotice()
    {
        $params =  unserialize($_COOKIE['params']) ;
        $contents = file_get_contents($params['url'].'send.log');
        //格式优化
         $contents=json_decode($contents,true);
        // $contents->data = json_decode($contents->data);
      
        // 实例化客户端
        $client = new PCCashier;

        $sign = $contents['sign']; //签名
        $data = stripslashes($contents['data']); //支付数据
        $verifyFlag = $client->verify($data, $sign);

        $contents['data'] = json_decode($contents['data']);

        //var_dump($contents['data']);
        return  json_encode([
            'verify'    => $verifyFlag==true?'验签成功!':'验签失败!',
            'json'=> json_encode($contents),
            'html'=> '<textarea id="code" name="code" rows="50" cols="10">异步通知返回结果（https://ylui.vegclubs.com/send.log）</textarea>',
            'url' => 'https://open.sandpay.com.cn/product/detail/43301/43332/44142'
        ]);
    }



}

$test = new Test();
$method=$_GET['method'];
if(empty($method)){
    return  json_encode([
        'json'=>[],
        'html'=> '<textarea id="code" name="code" rows="50" cols="10">请选择调用接口</textarea>'
    ]);
}else{
    echo $test->$method();
}

// $test->orderCreate();
// $test->orderRefund();
// $test->clearfileDownload();



