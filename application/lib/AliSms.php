<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2019/12/12
 * Time: 15:13
 */

namespace app\lib;
class AliSms
{
    private $accessKeyId;
    private $accessSecret;
    private $signName;
    private $templateCode;
    const APIHOST = 'https://dysmsapi.aliyuncs.com';
    function __construct($accessKeyId,$accessSecret,$signName,$templateCode){
        $this->accessKeyId = $accessKeyId;
        $this->accessSecret = $accessSecret;
        $this->signName = $signName;
        $this->templateCode = $templateCode;
    }
    public function sendSMS($mobile,$code){
        $parameters = [
            'AccessKeyId' => $this->accessKeyId,
            'Action' => 'SendSms',
            'RegionId' => 'cn-hangzhou',
            'Format' => 'json',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => randStr(10),
            'SignatureVersion' => '1.0',
            'Timestamp' => $this->utc_time(),
            'Version' => '2017-05-25',
            'PhoneNumbers' => $mobile,
            'SignName' => $this->signName,
            'TemplateCode' => $this->templateCode,
            'TemplateParam' => json_encode([
                'code'=> $code
            ])
        ];
        $parameters['Signature'] = $this->sign($parameters);
        return $this->doPost($parameters);
    }
    public function paySms($mobile,$code){
        $parameters = [
            'AccessKeyId' => $this->accessKeyId,
            'Action' => 'SendSms',
            'RegionId' => 'cn-hangzhou',
            'Format' => 'json',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => randStr(10),
            'SignatureVersion' => '1.0',
            'Timestamp' => $this->utc_time(),
            'Version' => '2017-05-25',
            'PhoneNumbers' => $mobile,
            'SignName' => $this->signName,
            'TemplateCode' => 'SMS_215343754',
            'TemplateParam' => json_encode([
                'status'=> $code
            ])
        ];
        $parameters['Signature'] = $this->sign($parameters);
        return $this->doPost($parameters);
    }

    public function numSms($mobile){
        $parameters = [
            'AccessKeyId' => $this->accessKeyId,
            'Action' => 'SendSms',
            'RegionId' => 'cn-hangzhou',
            'Format' => 'json',
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => randStr(10),
            'SignatureVersion' => '1.0',
            'Timestamp' => $this->utc_time(),
            'Version' => '2017-05-25',
            'PhoneNumbers' => $mobile,
            'SignName' => $this->signName,
            'TemplateCode' => 'SMS_219746549',
        ];
        $parameters['Signature'] = $this->sign($parameters);
        return $this->doPost($parameters);
    }
    private function doPost($parameters){
        $res = Curl::main()->url(self::APIHOST.'?'.http_build_query($parameters))->get();
        return $this->checkErr($res);
    }
    private function checkErr($res){
        $data = json_decode($res,true);
        if($data['Code']=='OK'){
            return true;
        }else{
            return false;
        }
    }
    //获取UTC格式的时间
    private function utc_time(){
        date_default_timezone_set('GMT');
        $timestamp = new \DateTime();
        $timeStr = $timestamp->format("Y-m-d\TH:i:s\Z");
        return $timeStr;
    }
    private function sign($parameters){
        $string = self::rpcString('GET',$parameters);
        return base64_encode(hash_hmac('sha1', $string, $this->accessSecret.'&', true));
    }
    private static function rpcString($method, array $parameters)
    {
        ksort($parameters);
        $canonicalized = '';
        foreach ($parameters as $key => $value) {
            $canonicalized .= '&' . self::percentEncode($key) . '=' . self::percentEncode($value);
        }

        return $method . '&%2F&' . self::percentEncode(substr($canonicalized, 1));
    }
    private static function percentEncode($string)
    {
        $result = urlencode($string);
        $result = str_replace(['+', '*'], ['%20', '%2A'], $result);
        $result = preg_replace('/%7E/', '~', $result);

        return $result;
    }
}