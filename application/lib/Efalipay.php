<?php
namespace app\lib;
class Efalipay {
    //测试环境主扫接口路径
    protected $gateway = 'http://test-efps.epaylinks.cn/api/txs/pay/NativePayment';
    //测试环境单笔提现接口路径
    protected $withdrawalToCard = 'http://test-efps.epaylinks.cn/api/txs/pay/withdrawalToCard';
    //进件
    protected $apply_url = 'http://test-efps.epaylinks.cn/api/cust/SP/Merchant/apply';
    //生产环境接口路径
    //protected $gateway = 'https://efps.epaylinks.cn/api/txs/pay/NativePayment';
	//测试环境绑卡接口路径
    protected $gateways = 'http://test-efps.epaylinks.cn/api/txs/protocol/bindCard';
	//测试环境绑卡确认接口路径
    protected $bindCardConfirm = 'http://test-efps.epaylinks.cn/api/txs/protocol/bindCardConfirm';
	//测试环境交易接口路径
    protected $protocolPayPre = 'http://test-efps.epaylinks.cn/api/txs/protocol/protocolPayPre';
    //测试环境交易确认接口路径
    protected $protocolPayConfirm = 'http://test-efps.epaylinks.cn/api/txs/protocol/protocolPayConfirm';
    //私钥文件路径
    public $rsaPrivateKeyFilePath = "./cert/user-rsa.pfx";
    //易票联测试公钥    
    public $publicKeyFilePath = "./cert/EFPS-PublicKey.cer";
    //证书序列号
    private  $sign_no='562959004039810001';
    //证书密码
    private $password='asdf123456';
    //编码格式
    public $charset = "UTF-8";    
    public  $signType = "RSA2";    
    //商户号
    protected $config     = array(
        'customer_code'   => '562959004039810',
        'notify_url' => 'http://www.baidu.com',
        'return_url' => 'http://www.baidu.com'
    );
    //商户号
    private $customer_code = '562959004039810';
    const URL = 'https://efps.epaylinks.cn';
//    const URL = 'http://test-efps.epaylinks.cn';

    /**
     * 云闪付3.0支付
     * @param $orderNo 订单号
     * @param $amount 订单金额（分）
     * @param $notify_url 回调地址
     * @return void
     */
    public function payPal($orderNo,$amount,$notify_url){
        $client_ip = "127.0.0.1";
        if (getenv('HTTP_CLIENT_IP')) {
            $client_ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
            $client_ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('REMOTE_ADDR')) {
            $client_ip = getenv('REMOTE_ADDR');
        } else {
            $client_ip = $_SERVER['REMOTE_ADDR'];
        }
        $amount = round($amount,2) * 100;
        $orderInfo=array();
        $orderInfo['Id'] = $orderNo;
        $orderInfo['businessType'] = '130001';
        $orderInfo['goodsList'] = array(array('name'=>'pay','number'=>'one','amount'=>1));
        $param = array(
            'outTradeNo' => $orderNo,
            'customerCode' => $this->customer_code,
            'clientIp' => $client_ip,
            'orderInfo' => $orderInfo,
            'payAmount' => $amount,
            'payCurrency' => 'CNY',
            'notifyUrl' =>$notify_url,
            'transactionStartTime' =>date('YmdHis'),
            'nonceStr' => 'pay'.rand(100,999),
            'version' => '3.0',
        );
        $sign = $this->sign(json_encode($param));
        $request = $this->http_post_json(self::URL.'/api/txs/pay/UnionAppPayment',json_encode($param),$sign);
        return $request;
    }
    public function check() {
        if (!$this->config['customer_code'] ) {
            E("支付设置有误！");
        }
        return true;
    }
    //测试主扫
    public function buildRequestForm() {
        $orderNo = "123456".date('YmdHis');
        
        echo '订单号:'.$orderNo;
        echo '<br>';
        $client_ip = "127.0.0.1";
        if (getenv('HTTP_CLIENT_IP')) {
            $client_ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
            $client_ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('REMOTE_ADDR')) {
            $client_ip = getenv('REMOTE_ADDR');
        } else {
            $client_ip = $_SERVER['REMOTE_ADDR'];
        }
        
        $orderInfo=array();
        $orderInfo['Id'] = $orderNo;
        $orderInfo['businessType'] = '130001';
        $orderInfo['goodsList'] = array(array('name'=>'pay','number'=>'one','amount'=>1));
        //$orderInfo = json_encode($orderInfo);
        
        $param = array(
            'outTradeNo' => $orderNo,
            'customerCode' => $this->config['customer_code'],
            'clientIp' => $client_ip,
            'orderInfo' => $orderInfo,
            'payMethod'  => 7,
            'payAmount' => 10,
            'payCurrency' => 'CNY',
            'channelType' =>'02',
            'notifyUrl' =>$this->config['notify_url'],
            'redirectUrl' =>$this->config['return_url'],
            'transactionStartTime' =>date('YmdHis'),
            'nonceStr' => 'pay'.rand(100,999),
            'version' => '3.0',
			'areaInfo'=> '440103'	
        );
        $sign = $this->sign(json_encode($param));
        
        echo '发送的参数'.json_encode($param);
        echo '<br>签名值'.$sign;
        
        $request = $this->http_post_json($this->gateway,json_encode($param),$sign);
        if($request && $request[0] == 200){
            //           $re_data = json_decode($request[1],true);
            //           if($re_data['returnCode'] == '0000'){
            //                $payurl = $re_data['codeUrl'];
            //         $sHtml="<script language='javascript' type='text/javascript'>window.location.href='{$payurl}';</script>";
            echo '<br>'.'获取到的参数：';
            //               echo $request[1];
            return $request[1];
            /*             }else{
             echo $request[1];
             exit;
             } */
            
        }else{
            print_r($request);
            exit;
        }
        exit;
        //return "";
    }
    
    //测试单笔提现
    public function withDraw() {
        $orderNo = "tx123".date('YmdHis');
        
        echo '订单号:'.$orderNo;
        echo '<br>';
        $param = array(
            'outTradeNo' => $orderNo,
            'customerCode' => $this->config['customer_code'],
            'amount' => 10,
            'bankUserName' =>$this->public_encrypt('张三'),
            'bankCardNo' => $this->public_encrypt('6214858888883338'),
            'bankName' => '招商银行',
            'bankAccountType' =>'2',
            'payCurrency' => 'CNY',
            'notifyUrl' =>$this->config['notify_url'],
            'nonceStr' => 'pay'.rand(100,999),
        );        
        $sign = $this->sign(json_encode($param));        
        echo '发送的参数'.json_encode($param);
        echo '<br>签名值'.$sign;        
        $request = $this->http_post_json($this->withdrawalToCard,json_encode($param),$sign);
        if($request && $request[0] == 200){
            echo '<br>'.'获取到的参数：';
            return $request[1];            
        }else{
            print_r($request);
            exit;
        }
        exit;

    }
    
    //进件
    //发起进件
    public function apply(){        
        $paper = '{"certificateName":"李四","contactPhone":"13531231222","email":"test1@test.cn","lawyerCertNo":"430481198104234557","lawyerCertType":"0","merchantType":"3","openBank":"中国银行","openingLicenseAccountPhoto":"https://www.epaylinks.cn/www/wimages/epl_logo.png","settleAccount":"李四","settleAccountNo":"6214830201234567","settleAccountType":"2","settleTarget":"2","legalPersonPhone":"13430293947","registeredCapital":"0"}';        
        $business = array(
            array(
                "businessCode"=>"WITHDRAW_TO_SETTMENT_DEBIT",
                "creditcardsEnabled"=>0,
                "refundEnabled"=>1,
                "refundFeePer"=>0,
                "refundFeeRate"=>0,
                "settleCycle"=>"D+0",
                "stage"=>array(
                        array(
                            "amountFrom"=>0,
                            "feePer"=>50
                        )
                    )
            ) 
        );  
        $param =array(
            'acqSpId'       =>  $this->config['customer_code'],
			'version'  => "2.0",			
            'merchantName'  => "测试商户20230602",
            'acceptOrder'   => 0,
            'openAccount'   => 1,
            'paper' => $paper,
            'business' =>$business
       );
        $sign = $this->sign(json_encode($param));


        echo json_encode($param);        

       $res = $this->http_post_json($this->apply_url,json_encode($param),$sign);
        var_dump($res);
        die;
    }

    /**
     * 绑定银行卡
     * @param $bankCardNo //银行卡号
     * @param $userName //用户名
     * @param $phoneNum //手机号
     * @param $certificatesNo //身份证号
     * @return mixed|void
     */
    public function buildRequestForms($bankCardNo,$userName,$phoneNum,$certificatesNo,$member_id) {
        $mchtOrderNo = randStr(5, false, false).date('YmdHis');
        $param = array(
            'mchtOrderNo' => $mchtOrderNo,
            'customerCode' => $this->customer_code,
			'memberId' => $member_id,    //自编
            'bankCardNo' => $this->public_encrypt($bankCardNo),
			'userName' => $this->public_encrypt($userName),
			'certificatesNo' => $this->public_encrypt($certificatesNo),
			'phoneNum' => $this->public_encrypt($phoneNum),
            'bankCardType' => 'debit',
            'nonceStr' => rand(100,999),
            'certificatesType'  => '01',
            'transType' => '01',
            'version' => '3.0',
        );
        $sign = $this->sign(json_encode($param));
        $request = $this->http_post_json(self::URL.'/api/txs/protocol/bindCard',json_encode($param),$sign);
        return $request;
    }

    /**
     * 绑卡确认
     * @param $memberId
     * @param $smsCode
     * @param $smsNo
     * @return array
     */
    public function confirmRequest($memberId,$smsCode,$smsNo) {
        $param = array(
			'customerCode' => $this->customer_code,
            'memberId' => $memberId,
			'nonceStr' => rand(100,999),
			'smsCode' => $smsCode,
			'smsNo' => $smsNo,
            'version' => '3.0',         
        );
        $sign = $this->sign(json_encode($param));
        $request = $this->http_post_json(self::URL.'/api/txs/protocol/bindCardConfirm',json_encode($param),$sign);
        return $request;
    }
    /**
     * 交易
     * @param $orderNo
     * @param $protocol
     * @param $amount
     * @param $data
     * @param $notify_url
     * @param $orderInfo
     * @return array
     */
    public function protocolPayPreRequest($orderNo,$protocol,$amount,$data,$notify_url,$orderInfo) {
        $amount = round($amount,2) * 100;
        $orderInfo=array();
        $orderInfo['Id'] = $orderNo;
        $orderInfo['businessType'] = '130001';
        $orderInfo['goodsList'] = array(array('name'=>'pay','number'=>'one','amount'=>1));
        $param = array(
            'version' => '3.0',
            'customerCode' => $this->customer_code,
            'outTradeNo' => $orderNo,
            'protocol'=>$protocol,
            'orderInfo' => $orderInfo,
            'payAmount' => $amount,
            'payCurrency' => 'CNY',
            'transactionStartTime' =>date('YmdHis'),
            'isSendSmsCode' => '2',
            'nonceStr' => 'pay'.rand(100,999),
            'notifyUrl' =>$notify_url,
            'attachData' => $data
        );
        $sign = $this->sign(json_encode($param));
        $request = $this->http_post_json(self::URL.'/api/txs/protocol/protocolPayPre',json_encode($param),$sign);
        return $request;
    }

    /**
     * 支付宝扫码支付
     * @param $orderNo
     * @param $amount
     * @param $data
     * @param $notify_url
     * @param $orderInfo
     * @return void
     */
    public function aliPayPreRequest($orderNo,$amount,$data,$notify_url,$orderInfo){
        $amount = round($amount,2) * 100;
        $orderInfo=array();
        $orderInfo['Id'] = $orderNo;
        $orderInfo['businessType'] = '130001';
        $orderInfo['goodsList'] = array(array('name'=>'pay','number'=>'one','amount'=>1));
        $param = array(
            'version' => '3.0',
            'customerCode' => $this->customer_code,
            'outTradeNo' => $orderNo,
            'orderInfo' => $orderInfo,
            'payMethod'=>7,
            'payAmount' => $amount,
            'payCurrency' => 'CNY',
            'transactionStartTime' =>date('YmdHis'),
            'nonceStr' => 'pay'.rand(100,999),
            'notifyUrl' =>$notify_url,
            'attachData' => $data
        );
        $sign = $this->sign(json_encode($param));
        $request = $this->http_post_json(self::URL.'/api/txs/pay/NativePayment',json_encode($param),$sign);
        return $request;
    }

    /**
     * 交易确认
     * @param $token
     * @param $protocol
     * @param $smsCode
     * @return mixed|void
     */
    public function payconfirmRequest($token,$protocol,$smsCode) {
        $param = array(
			'customerCode' => $this->customer_code,
			'smsCode' => $smsCode,
			'nonceStr' => rand(100,999),
			'token' => $token,
			'protocol' => $protocol,
            'version' => '3.0',         
        );
        $sign = $this->sign(json_encode($param));
        $request = $this->http_post_json(self::URL.'/api/txs/protocol/protocolPayConfirm',json_encode($param),$sign);
        return $request;
    }

    /**
     * 解绑银行卡
     * @param $memberId
     * @param $protocol
     * @return array
     */
	public function unbindBankCard($memberId,$protocol){
        $param = array(
            'customerCode' => $this->customer_code,
            'memberId' => $memberId,
            'nonceStr' => round(100,999),
            'protocol' => $protocol,
            'version' => '2.0',
        );
        $sign = $this->sign(json_encode($param));
        $request = $this->http_post_json(self::URL.'/api/txs/protocol/unBindCard',json_encode($param),$sign);
        return $request;
    }
    public function generateSign($params) {
        return $this->sign($this->getSignContent($params));
    }
    
    public function rsaSign($params) {
        return $this->sign($this->getSignContent($params));
    }
    
    protected function getSignContent($params) {
        ksort($params);
        
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                // 转换成目标字符集
                $v = $this->characet($v, $this->charset);
                
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                
                $i++;
            }
        }
        
        unset ($k, $v);
        
        return $stringToBeSigned;
    }
	
    /**
	*签名
	**/
    protected function sign($data) {
        
        $certs = array();
        openssl_pkcs12_read(file_get_contents($this->rsaPrivateKeyFilePath), $certs, $this->password); //其中password为你的证书密码
        
        ($certs) or die('请检查RSA私钥配置');
        
        openssl_sign($data, $sign, $certs['pkey'],OPENSSL_ALGO_SHA256);
        
        $sign = base64_encode($sign);
        return $sign;
    }
    
    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *    if is null , return true;
     **/
    protected function checkEmpty($value) {
        if (!isset($value))
            return true;
            if ($value === null)
                return true;
                if (trim($value) === "")
                    return true;
                    
                    return false;
    }
    

    public function rsaCheckV2($params,$sign) {
        //$sign = $params['sign'];
        //$params['sign'] = null;
        return $this->verify($params, $sign);
    }
    
    //使用易票联公钥验签    //返回的验签字段有中文需要加JSON_UNESCAPED_UNICODE才能验签通过
	//$data2 = json_encode($data, JSON_UNESCAPED_UNICODE);
    function verify($data, $sign) {
        //读取公钥文件
        $pubKey = file_get_contents($this->publicKeyFilePath);
        
        $res = openssl_get_publickey($pubKey);
        
        ($res) or die('RSA公钥错误。请检查公钥文件格式是否正确');
        //调用openssl内置方法验签，返回bool值
        $result = (bool)openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
        
        if(!$this->checkEmpty($this->publicKeyFilePath)) {
            //释放资源
            openssl_free_key($res);
        }
        
        return $result;
    }
    
    //使用易票联公钥加密
    function public_encrypt($data)
    {
        //读取公钥文件
        $pubKey = file_get_contents($this->publicKeyFilePath);
        
        $res = openssl_get_publickey($pubKey);
        
        ($res) or die('RSA公钥错误。请检查公钥文件格式是否正确');
        
        $crypttext = "";
        
        openssl_public_encrypt($data,$crypttext, $res );
        
       
        if(!$this->checkEmpty($this->publicKeyFilePath)) {
            //释放资源
            openssl_free_key($res);
        }
        
        return(base64_encode($crypttext));

    }
    
    /**
     * 转换字符集编码
     * @param $data
     * @param $targetCharset
     * @return string
     */
    function characet($data, $targetCharset) {
        
        
        if (!empty($data)) {
            $fileType = $this->charset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                
                $data = mb_convert_encoding($data, $targetCharset);
                //				$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }
        
        
        return $data;
    }
    
    protected function getParam($para) {
        $arg = "";
        while (list ($key, $val) = each($para)) {
            $arg.=$key . "=" . $val . "&";
        }
        //去掉最后一个&字符
        $arg = substr($arg, 0, -1);
        return $arg;
    }
    
    /**
     * 获取远程服务器ATN结果,验证返回URL
     * @param $notify_id
     * @return
     * 验证结果集：
     * invalid命令参数不对 出现这个错误，请检测返回处理中partner和key是否为空
     * true 返回正确信息
     * false 请检查防火墙或者是服务器阻止端口问题以及验证时间是否超过一分钟
     */
    protected function getResponse2($Params) {
        $veryfy_url = $this->gateway . "?" . $Params;
        $responseTxt = $this->fsockOpen($veryfy_url);
        return $responseTxt;
    }
    
    protected function http_post_json($url, $jsonStr,$sign)
    {
        $ch = curl_init();
        $headers = array(
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($jsonStr),
            'x-efps-sign-no:'.$this->sign_no,
            'x-efps-sign-type:SHA256withRSA',
            'x-efps-sign:'.$sign,
            'x-efps-timestamp:'.date('YmdHis'),
        );
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // 跳过检查        
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);  // 跳过检查
        //curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        return array($httpCode, $response);
    }
    
   
}


?>
