<?php
namespace app\lib\JytPay;

/**
 * @author liyabin
 * Class Config
 * @package JytPay\Client
 */
class Config{
    // 商户测试服务器地址
    
    
    
    public $url='https://onepay.jytpay.com/onePayService/onePay.do';
    //https://onepay.jytpay.com/onePayService/onePay.do

	// 测试商户号（可替换成自己入网的商户号）
    public $merchant_id='332071000001';

    // 自签证书：pem格式密钥文件
    public $cer_path='/certs/290071040001_jyt_pub.pem'; // 平台公钥文件
    public $pfx_path='/certs/290071040001_mer_pri.pem'; // 商户私钥文件

    // 使用三方证书的私钥（目前未用到）
    public $pfx_password = 'password';
    
    /*
    public $url='https://test.jytpay.com/onePayService/onePay.do';

	// 测试商户号（可替换成自己入网的商户号）
    public $merchant_id='290071040001';

    // 自签证书：pem格式密钥文件
    public $cer_path='/certs/290071040001_jyt_pub.pem'; // 平台公钥文件
    public $pfx_path='/certs/290071040001_mer_pri.pem'; // 商户私钥文件

    // 使用三方证书的私钥（目前未用到）
    public $pfx_password = 'password';
    */
}