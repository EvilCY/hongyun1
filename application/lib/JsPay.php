<?php
/**
 * Created by PhpStorm.
 * User: Vnser
 * File: WxPay.class.php
 * Date: 16-5-4
 * Time: 下午3:51
 */

namespace app\lib;
class JsPay
{

    #微信支付链接
    static protected $link = [
        'unifiedorder' => 'https://api.mch.weixin.qq.com/pay/unifiedorder',//同一下单接口
        'transfers' => 'https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers',
        'refund'=>'https://api.mch.weixin.qq.com/secapi/pay/refund',
        'sendredpack' => 'https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack'//普通红包接口
    ];
    protected $error = '';
    #微信支付配置信息
    protected $config = [
        
        'appid' => 'wx3dc7c6f16d8c684c',//公众号APPID
        'key' => '7mAMbkNfO49ydClhTfrGnFZcpcAS4cTb',//微信支付商户密钥
        'mchid' => '1613542779',//微信支付商户号
        'cert' => null,//证书
        /*
        'appid' => 'wx3dc7c6f16d8c684c', //'wx3dc7c6f16d8c684c',//公众号APPID
        'key' => 'Hongyunchuanshang005813927147486', //'7mAMbkNfO49ydClhTfrGnFZcpcAS4cTb',//微信支付商户密钥
        'mchid' => '1658192193',//'1613542779',//微信支付商户号
        'cert' => null,//证书
        */
        
    ];
    /**
     * 数组转换为xml字符串
     * @param array $arr
     * @return string $str
     * */
    static protected function xml_encode($arr)
    {
        $str = '<xml>';
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $str .= "<{$key}>{$val}</{$key}>";
            } else {
                $str .= "<{$key}><![CDATA[{$val}]]></{$key}>";
            }
        }
        $str .= "</xml>";
        return $str;
    }

    /**
     * 发送post请求
     * @param string $url 请求地址
     * @param string $data 数据
     * @param bool $is_cert 是否设置证书
     * @return mixed
     * */
    protected function post($url, $data, $is_cert = false)
    {
        $resCon = Curl::main()->url($url);
        if ($is_cert) {
            $resCon = $resCon->certificate($this->config['cert']['certPath'], $this->config['cert']['keyPath']);
        }
        $resCon = $resCon->post($data);
        if ($resCon === false) {
            $this->error = Curl::main()->getError();
            return false;
        }
        $resArr = (array)simplexml_load_string($resCon, 'SimpleXMLElement', LIBXML_NOCDATA);
        return $this->checkError($resArr);
    }

    /**
     * 检查调微信支付接口返回内容是否有错
     * @param array $data
     * @return mixed
     * */
    protected function checkError($data)
    {
        if ($data['return_code'] == 'FAIL') {
            $this->error = $data['return_msg'];
            return false;
        } elseif ($data['result_code'] == 'FAIL') {
            $this->error = $data['err_code_des'];
            return false;
        }
        return $data;
    }

    /**
     * 获取上级接口调用失败错误信息
     * @return string
     * */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 签名处理
     * @param array $arr 要签名数据
     * @return string
     * */
    protected function sign($arr)
    {
        #ASSI排序处理
        ksort($arr);
        $stringA = urldecode(http_build_query($arr)) . "&key={$this->config['key']}";
        $sign = strtoupper(md5($stringA));
        return $sign;
    }

    /**
     * 统一下单接口
     * @param array $dataArr
     * @return array
     * */
    public function sameOrder($body, $out_trade_no, $total_fee,$notify_url,$open_id)
    {
        #设置随机字符串
        $dataArr['nonce_str'] = randStr(32);
        #设置appid
        $dataArr['appid'] = $this->config['appid'];
        #设置商户号
        $dataArr['mch_id'] = $this->config['mchid'];
        #订单生成时间
        $dataArr['time_start'] = date('YmdHis');
        #订单失效时间
        $dataArr["openid"] = $open_id;
        $dataArr["body"] = $body;
        $dataArr["total_fee"] = $total_fee;
        $dataArr["trade_type"] = "JSAPI";
        $dataArr["notify_url"] = $notify_url;
        $dataArr["out_trade_no"] = $out_trade_no;
        $dataArr['time_expire'] = date('YmdHis', time() + 7200);
        #客户端操作ip
        $dataArr['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];
        #签名处理
        $dataArr['sign'] = $this->sign($dataArr);
        #转换为xml
        $dataXml = self::xml_encode($dataArr);
        #发送post请求
        $result =  $this->post(self::$link['unifiedorder'], $dataXml);
        return $result;
    }

    /**
     * 退款接口
     * @param array $data
     * @return mixed
     */
    public function refund($data = []){
        $data = array_merge(array(
            'appid'=>$this->config['appid'],
            'mch_id'=>$this->config['mchid'],
            'nonce_str'=>randStr(32),
         /*   'out_refund_no'=>'',//退款单号
            'out_trade_no'=>'',//退款订单号
            'transaction_id'=>'',//微信支付订单号
            'total_fee'=>'',//订单金额
            'refund_fee'=>'',//退款金额*/
            'op_user_id'=>$this->config['mchid']
        ),$data);
        //签名
        $data['sign'] = $this->sign($data);
        $dataXml = self::xml_encode($data);
        return $this->post(self::$link['refund'],$dataXml,true);
    }

    /**
     *
     * 接口下单后js回调json参数
     * @param string $prepay_id 微信订单号
     * @return string
     * */
    public function getJsApiParameters($prepay_id)
    {
        $data = [
            'appId' => $this->config['appid'],
            'nonceStr' => randStr(32),
            'package' => 'prepay_id=' . $prepay_id,
            'signType' => 'MD5',
            'timeStamp' => "" . time()
        ];

        $data['paySign'] = $this->sign($data);
        return $data;
    }
    /**
     * 企业付款业务是基于微信支付商户平台的资金管理能力
     * @param string $partner_trade_no 商户订单号
     * @param string $openid 收款方用户微信openid
     * @param string $re_user_name 收款方姓名
     * @param float $amount 转账金额，单位为分
     * @param string $desc 企业付款操作说明信息。必填。
     * @param string $check_name 校验用户姓名选项 说明：NO_CHECK：不校验真实姓名
     * FORCE_CHECK：强校验真实姓名（未实名认证的用户会校验失败，无法转账）
     * OPTION_CHECK：针对已实名认证的用户才校验真实姓名（未实名认证用户不校验，可以转账成功）
     * @return bool|array
     * */
    public function transfers($partner_trade_no, $openid, $re_user_name, $amount, $desc, $check_name = 'NO_CHECK')
    {
        $data = array(
            'mch_appid' => $this->config['appid'],
            'mchid' => $this->config['mchid'],
            'device_info' => '1000',
            'nonce_str' => randStr(32),
            'partner_trade_no' => $partner_trade_no,
            'openid' => $openid,
            'check_name' => $check_name,
            're_user_name' => $re_user_name,
            'amount' => ($amount * 100),
            'desc' => $desc,
            'spbill_create_ip' => $_SERVER['SERVER_ADDR']
        );

        $data['sign'] = $this->sign($data);
        $xmldata = self::xml_encode($data);
        return $this->post(self::$link['transfers'], $xmldata, true);
    }

    /**
     * 验证数组中签名和对应数据签名是否正确
     * @param array $valArr 参数
     * @return bool
     * */
    public function checkSign($valArr)
    {
        $sign = $valArr['sign'];
        unset($valArr['sign']);
        $zSign = $this->sign($valArr);
        return $sign == $zSign;
    }

    /**
     * 返回微信支付结果
     * */
    public function returnWxpayResult($status, $msg='')
    {
        header('Content-Type: application/xml;charset=utf-8');
        $code = $status ? 'SUCCESS' : 'FAIL';
        $str = "<xml>
  <return_code><![CDATA[{$code}]]></return_code>
  <return_msg><![CDATA[$msg]]></return_msg>
</xml>";
        exit($str);
    }

    /**
     * 随机产生字符串
     * @param int $num
     * @return string $Rstr
     * */
    static private function random($num=1){
        /*产生随机1-9a-Z*/
        $strR = '1234567890qwertyuiopasdfghjklzxcvbnm';
        $lenR = strlen($strR);
        $Rstr = null;
        for($i=0;$i<$num;$i++){
            $thisN = $strR[mt_rand(0,$lenR-1)];
            $Rstr .= mt_rand(0,1)==1?strtoupper($thisN):$thisN;
        }
        return $Rstr;
    }

    /**
     *发送普通红包接口
     * @return boolean false | array
     */
    public function sendredpack($data){
        #随机字符串
        $data['nonce_str'] = self::random(32);
        #设置appid
        $data['wxappid'] = $this->config['appid'];
        #设置商户号
        $data['mch_id'] = $this->config['mchid'];
        #红包总人数
        $data['total_num'] = 1;
        #客户端操作ip
        $data['client_ip'] =$_SERVER['REMOTE_ADDR'];
        #签名
        $data['sign'] = $this->sign($data);
        #生成xml
        $xmlData = self::xmlStr($data);
        #发送请求
        return $this->post_ssl(self::$link['sendredpack'],$xmlData);
    }

    /**
     * 数组转换为xml字符串
     * @param array $arr
     * @return string $str
     * */
    static private  function xmlStr($arr){
        $str = '<xml>';
        foreach($arr as $key => $val){
            if(is_numeric($val)){
                $str .= "<{$key}>{$val}</{$key}>";
            }else{
                $str .= "<{$key}><![CDATA[{$val}]]></{$key}>";
            }

        }
        $str .= "</xml>";
        return $str;
    }
    /**
     * 发送post请求----带证书
     * @param string $url 请求地址
     * @param string $data 数据
     * @return mixed
     * */
    private function post_ssl($url,$data){
        $resCon = Curl3::main()->url($url)->post($data);

        $resArr = (array)simplexml_load_string($resCon,'SimpleXMLElement',LIBXML_NOCDATA);

        return $this->checkError($resArr);
    }
} 