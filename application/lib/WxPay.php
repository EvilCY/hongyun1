<?php
/**
 * Created by PhpStorm.
 * User: LiuHong
 * File: WxPay.class.php
 * Date: 21-8-6
 * Time: 下午3:51
 */

namespace app\lib;
Class WxPay
{
    //微信开放平台的应用appid
    private $appid = 'wx6d5c5d5d8db042ab';
    //商户号（注册商户平台时，发置注册邮箱的商户id）
    private $partnerId = '1642736592';
    //商户平台api支付处设置的key
    private $key = 'qwertyuiopasdfghjklzxcvbNM987654';
    //支付请求地址
    const URL = 'https://api.mch.weixin.qq.com/pay/unifiedorder';


    //生成订单
    public function wechat_pay($body, $out_trade_no, $total_fee,$notify_url)
    {
        $data["appid"] = $this->appid;
        $data["body"] = $body;
        $data["mch_id"] = $this->partnerId;
        $data["nonce_str"] = $this->getRandChar(32);
        $data["notify_url"] = $notify_url;
        $data["out_trade_no"] = $out_trade_no;
        $data["spbill_create_ip"] = $this->get_client_ip();
        $data["total_fee"] = $total_fee;
        $data["trade_type"] = "APP";
        //按照参数名ASCII字典序排序并且拼接API密钥生成签名
        $s = $this->getSign($data);
        $data["sign"] = $s;
        //配置xml最终得到最终发送的数据
        $xml = $this->arrayToXml($data);
        $response = $this->postXmlCurl($xml, self::URL);
        //将微信返回的结果xml转成数组
        $returnOne = $this->xmlstr_to_array($response);
        $params["package"] = "Sign=WXPay";
        $params["timestamp"] = strval(time());
        $arr['appid'] = $returnOne['appid'];
        $arr['noncestr'] = $returnOne['nonce_str'];
        $arr['package'] = $params["package"];//正常key是package(改成package_bak,安卓有冲突)
        $arr['prepayid'] = $returnOne['prepay_id'];
        $arr['partnerid'] = $returnOne['mch_id'];
        $arr['timestamp'] = $params["timestamp"];
        //重新生成签名
        $str = 'appid='.$arr['appid'].'&noncestr='.$returnOne['nonce_str'].'&package=Sign=WXPay&partnerid='. $returnOne['mch_id'].'&prepayid='.$arr['prepayid'].'&timestamp='.$arr['timestamp'];
        $arr['sign']=strtoupper(MD5($str.'&key='. $this->key));
        return $arr;
    }

    //进行签名
    function getSign($Obj)
    {
        foreach ($Obj as $k => $v) {
            $Parameters[strtolower($k)] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //echo "【string】 =".$String."</br>";
        //签名步骤二：在string后加入KEY
        $String = $String . "&key=" . $this->key;
        //签名步骤三：MD5加密
        $result_ = strtoupper(md5($String));
        return $result_;
    }
    //获取指定长度的随机字符串
    private function getRandChar($length)
    {
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($strPol) - 1;

        for ($i = 0; $i < $length; $i++) {
            $str .= $strPol[rand(0, $max)];//rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }

        return $str;
    }

    //获取当前服务器的IP
    function get_client_ip()
    {
        if ($_SERVER['REMOTE_ADDR']) {
            $cip = $_SERVER['REMOTE_ADDR'];
        } elseif (getenv("REMOTE_ADDR")) {
            $cip = getenv("REMOTE_ADDR");
        } elseif (getenv("HTTP_CLIENT_IP")) {
            $cip = getenv("HTTP_CLIENT_IP");
        } else {
            $cip = "unknown";
        }
        return $cip;
    }

    //将数组转成uri字符串
    function formatBizQueryParaMap($paraMap, $urlencode)
    {
        $buff = "";
        $reqPar = '';
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            $buff .= strtolower($k) . "=" . $v . "&";
        }
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }

    //数组转xml
    function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";

            } else
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
        }
        $xml .= "</xml>";
        return $xml;
    }

    //post https请求，CURLOPT_POSTFIELDS xml格式
    function postXmlCurl($xml, $url, $second = 30)
    {
        //初始化curl
        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        //这里设置代理，如果有的话
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            return false;
        }
    }

    /**
     * xml转成数组
     */
    public function xmlstr_to_array($xmlstr)
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xmlstr);
        return $this->domnode_to_array($doc->documentElement);
    }

    public function domnode_to_array($node)
    {
        $output = array();
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE:
            case XML_TEXT_NODE:
                $output = trim($node->textContent);
                break;
            case XML_ELEMENT_NODE:
                for ($i = 0, $m = $node->childNodes->length; $i < $m; $i++) {
                    $child = $node->childNodes->item($i);
                    $v = $this->domnode_to_array($child);
                    if (isset($child->tagName)) {
                        $t = $child->tagName;
                        if (!isset($output[$t])) {
                            $output[$t] = array();
                        }
                        $output[$t][] = $v;
                    } elseif ($v) {
                        $output = (string)$v;
                    }
                }
                if (is_array($output)) {
                    if ($node->attributes->length) {
                        $a = array();
                        foreach ($node->attributes as $attrName => $attrNode) {
                            $a[$attrName] = (string)$attrNode->value;
                        }
                        $output['@attributes'] = $a;
                    }
                    foreach ($output as $t => $v) {
                        if (is_array($v) && count($v) == 1 && $t != '@attributes') {
                            $output[$t] = $v[0];
                        }
                    }
                }
                break;
        }
        return $output;
    }

} 