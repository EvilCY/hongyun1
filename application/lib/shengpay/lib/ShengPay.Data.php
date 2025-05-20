<?php

require_once "ShengPay.Config.Interface.php";
require_once "ShengPay.Exception.php";
require_once "Constants.php";

class ShengpayData
{

    protected $values = array();

    /**
     * 设置签名，详见签名生成算法类型
     * @param string $sign_type
     **/
    public function setSignType($sign_type)
    {
        $this->values['signType'] = $sign_type;
        return $sign_type;
    }

    public function getSignType()
    {
        return $this->values['signType'];
    }

    /**
     * 设置签名，详见签名生成算法
     * @param string $config
     **/
    public function setSign($config)
    {
        $sign = $this->sign($config);
        $this->values['sign'] = $sign;
        return $sign;
    }

    /**
     * 获取签名，详见签名生成算法的值
     * @return 值
     **/
    public function getSign()
    {
        return $this->values['sign'];
    }

    /**
     * 判断签名，详见签名生成算法是否存在
     * @return true 或 false
     **/
    public function isSignSet()
    {
        return array_key_exists('sign', $this->values);
    }

    /**
     * 设置随机字符串
     * @param string $value
     **/
    public function setNonceStr($nonceStr)
    {
        $this->values['nonceStr'] = $nonceStr;
    }

    /**
     * 获取随机字符串
     * @return 值
     **/
    public function getNonceStr()
    {
        return $this->values['nonceStr'];
    }

    /**
     * 判断随机字符串是否存在
     * @return true 或 false
     **/
    public function isNonceStrSet()
    {
        return array_key_exists('nonceStr', $this->values);
    }

    /**
     * 格式化参数格式化成url参数
     */
    public function toUrlParams()
    {
        $buff = "";
        foreach ($this->values as $k => $v) {
            if ($k != "sign" && $v !== "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * 获取设置的值
     */
    public function getValues()
    {
        return $this->values;
    }
}

class ShengpayResult extends ShengpayData
{

    /**
     * @param string $returnCode
     **/
    public function setReturnCode($returnCode)
    {
        $this->values['returnCode'] = $returnCode;
    }

    /**
     * @return 值
     **/
    public function getReturnCode()
    {
        return $this->values['returnCode'];
    }

    /**
     * @param string $returnMsg
     **/
    public function setReturnMsg($returnMsg)
    {
        $this->values['returnMsg'] = $returnMsg;
    }

    /**
     * @return 值
     **/
    public function getReturnMsg()
    {
        return $this->values['returnMsg'];
    }


    /**
     * @param string $resultCode
     **/
    public function setResultCode($resultCode)
    {
        $this->values['resultCode'] = $resultCode;
    }

    /**
     * @return 值
     **/
    public function getResultCode()
    {
        return $this->values['resultCode'];
    }

    /**
     * @param string $errorCode
     **/
    public function setErrorCode($errorCode)
    {
        $this->values['errorCode'] = $errorCode;
    }

    /**
     * @return 值
     **/
    public function getErrorCode()
    {
        return $this->values['errorCode'];
    }

    /**
     * @param string $errorCodeDes
     **/
    public function setErrorCodeDes($errorCodeDes)
    {
        $this->values['errorCodeDes'] = $errorCodeDes;
    }

    /**
     * @return 值
     **/
    public function getErrorCodeDes()
    {
        return $this->values['errorCodeDes'];
    }

    /**
     * @param $config  配置对象
     * 检测签名
     */
    public function checkSign($config)
    {
        if (!$this->isSignSet()) {
            throw new ShengPayException("签名错误！");
        }
        if ($this->verifySign($config)) {
            //签名正确
            return true;
        }
        throw new ShengPayException("签名错误！");
    }

    /**
     * 验证签名
     * @param ShengPayConfigInterface $config 配置对象
     * @return bool
     */
    public function verifySign($config)
    {
        ksort($this->values);
        $string = $this->toUrlParams();
        if ($config->getSignType() == "MD5") {
            return md5($string . $config->getSdpPublicKey()) == $this->getSign();
        } else if ($config->getSignType() == "RSA") {
            $key = openssl_get_publickey($config->getSdpPublicKey());
            $sign = base64_decode($this->getSign());
            return openssl_verify($string, $sign, $key) === 1;
        } else {
            throw new ShengPayException("签名类型不支持！");
        }
    }


    /**
     *
     * 使用数组初始化
     * @param string $json
     */
    public function fromJson($json)
    {
        $this->values = json_decode($json, true);
    }

    /**
     * @param ShengPayConfigInterface $config 配置对象
     * @param string $json
     * @throws ShengPayException
     */
    public static function Init($config, $json)
    {
        $obj = new self();
        $obj->fromJson($json);
        $obj->checkSign($config);
        return $obj;
    }

}


abstract class ShengpayRequest extends ShengpayData
{
    public function getRequestUrl($isLive)
    {
        if ($isLive) {
            return Constants::PROD_SHENGPAY_AGGREGATE_URL . $this->getApiResource();
        } else {
            return Constants::TEST_SHENGPAY_AGGREGATE_URL . $this->getApiResource();
        }
    }

    protected abstract function getApiResource();

    /**
     * @return mixed
     */
    public function getMchId()
    {
        return $this->values['mchId'];
    }

    /**
     * @param mixed $mchId
     */
    public function setMchId($mchId)
    {
        $this->values['mchId'] = $mchId;
    }

    /**
     * 生成签名
     * @param ShengPayConfigInterface $config 配置对象
     * @param bool $needSignType 是否需要补signtype
     * @return 签名
     */
    public function sign($config, $needSignType = true)
    {
        if ($needSignType) {
            $this->setSignType($config->getSignType());
        }
        ksort($this->values);
        $string = $this->toUrlParams();
        if ($this->getSignType() == "MD5") {
            return md5($string . $config->getMchPrivateKey());
        } else if ($this->getSignType() == "RSA") {
            $key = openssl_get_privatekey($config->getMchPrivateKey());
            openssl_sign($string, $signature, $key);
            openssl_free_key($key);
            return base64_encode($signature);
        } else {
            throw new ShengPayException("签名类型不支持！");
        }
    }
}

class UnifiedOrderRequest extends ShengpayRequest
{


    protected function getApiResource()
    {
        return "/pay/unifiedorderOffline";
    }

    /**
     * @return mixed
     */
    public function getTradeType()
    {
        return $this->values['tradeType'];
    }

    /**
     * @param mixed $tradeType
     */
    public function setTradeType($tradeType)
    {
        $this->values['tradeType'] = $tradeType;
    }

    /**
     * @return mixed
     */
    public function getSdpAppId()
    {
        return $this->values['sdpAppId'];
    }

    /**
     * @param mixed $sdpAppId
     */
    public function setSdpAppId($sdpAppId)
    {
        $this->values['sdpAppId'] = $sdpAppId;
    }

    /**
     * @return mixed
     */
    public function getMchMemberInfo()
    {
        return $this->values['mchMemberInfo'];
    }

    /**
     * @param mixed $mchMemberInfo
     */
    public function setMchMemberInfo($mchMemberInfo)
    {
        $this->values['mchMemberInfo'] = $mchMemberInfo;
    }

    /**
     * @return mixed
     */
    public function getSubMchId()
    {
        return $this->values['subMchId'];
    }

    /**
     * @param mixed $subMchId
     */
    public function setSubMchId($subMchId)
    {
        $this->values['subMchId'] = $subMchId;
    }

    /**
     * @return mixed
     */
    public function getOutTradeNo()
    {
        return $this->values['outTradeNo'];
    }

    /**
     * @param mixed $outTradeNo
     */
    public function setOutTradeNo($outTradeNo)
    {
        $this->values['outTradeNo'] = $outTradeNo;
    }

    /**
     * @return mixed
     */
    public function getTotalFee()
    {
        return $this->values['totalFee'];
    }

    /**
     * @param mixed $totalFee
     */
    public function setTotalFee($totalFee)
    {
        $this->values['totalFee'] = $totalFee;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->values['currency'];
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->values['currency'] = $currency;
    }

    /**
     * @return mixed
     */
    public function getTimeExpire()
    {
        return $this->values['timeExpire'];
    }

    /**
     * @param mixed $timeExpire
     */
    public function setTimeExpire($timeExpire)
    {
        $this->values['timeExpire'] = $timeExpire;
    }

    /**
     * @return mixed
     */
    public function getNotifyUrl()
    {
        return $this->values['notifyUrl'];
    }

    /**
     * @param mixed $notifyUrl
     */
    public function setNotifyUrl($notifyUrl)
    {
        $this->values['notifyUrl'] = $notifyUrl;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->values['body'];
    }

    /**
     * @param mixed $body
     */
    public function setBody($body)
    {
        $this->values['body'] = $body;
    }

    /**
     * @return mixed
     */
    public function getDetail()
    {
        return $this->values['detail'];
    }

    /**
     * @param mixed $detail
     */
    public function setDetail($detail)
    {
        $this->values['detail'] = $detail;
    }

    /**
     * @return mixed
     */
    public function getClientIp()
    {
        return $this->values['clientIp'];
    }

    /**
     * @param mixed $clientIp
     */
    public function setClientIp($clientIp)
    {
        $this->values['clientIp'] = $clientIp;
    }

    /**
     * @return array
     */
    public function getAttach()
    {
        return json_decode($this->values['attach'], true);
    }

    /**
     * @param array $attach
     */
    public function setAttach($attach)
    {
        $this->values['attach'] = json_encode($attach, 320);
    }

    /**
     * @return mixed
     */
    public function getExtra()
    {
        return json_decode($this->values['extra'], true);
    }

    /**
     * @param array $extra
     */
    public function setExtra($extra)
    {
        $this->values['extra'] = json_encode($extra, 320);
    }

    /**
     * @return string
     */
    public function getIsNeedShare()
    {
        return $this->values['isNeedShare'];
    }

    /**
     * @param string $isNeedShare
     */
    public function setIsNeedShare($isNeedShare)
    {
        $this->values['isNeedShare'] = $isNeedShare;
    }


}

class PreUnifieAppletdOrderRequest extends ShengpayRequest
{


    protected function getApiResource()
    {
        return "/pay/preUnifieAppletdorder";
    }

    /**
     * @return mixed
     */
    public function getTradeType()
    {
        return $this->values['tradeType'];
    }

    /**
     * @param mixed $tradeType
     */
    public function setTradeType($tradeType)
    {
        $this->values['tradeType'] = $tradeType;
    }

    /**
     * @return mixed
     */
    public function getSdpAppId()
    {
        return $this->values['sdpAppId'];
    }

    /**
     * @param mixed $sdpAppId
     */
    public function setSdpAppId($sdpAppId)
    {
        $this->values['sdpAppId'] = $sdpAppId;
    }

    /**
     * @return mixed
     */
    public function getMchMemberInfo()
    {
        return $this->values['mchMemberInfo'];
    }

    /**
     * @param mixed $mchMemberInfo
     */
    public function setMchMemberInfo($mchMemberInfo)
    {
        $this->values['mchMemberInfo'] = $mchMemberInfo;
    }

    /**
     * @return mixed
     */
    public function getSubMchId()
    {
        return $this->values['subMchId'];
    }

    /**
     * @param mixed $subMchId
     */
    public function setSubMchId($subMchId)
    {
        $this->values['subMchId'] = $subMchId;
    }

    /**
     * @return mixed
     */
    public function getOutTradeNo()
    {
        return $this->values['outTradeNo'];
    }

    /**
     * @param mixed $outTradeNo
     */
    public function setOutTradeNo($outTradeNo)
    {
        $this->values['outTradeNo'] = $outTradeNo;
    }

    /**
     * @return mixed
     */
    public function getTotalFee()
    {
        return $this->values['totalFee'];
    }

    /**
     * @param mixed $totalFee
     */
    public function setTotalFee($totalFee)
    {
        $this->values['totalFee'] = $totalFee;
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->values['currency'];
    }

    /**
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->values['currency'] = $currency;
    }

    /**
     * @return mixed
     */
    public function getTimeExpire()
    {
        return $this->values['timeExpire'];
    }

    /**
     * @param mixed $timeExpire
     */
    public function setTimeExpire($timeExpire)
    {
        $this->values['timeExpire'] = $timeExpire;
    }

    /**
     * @return mixed
     */
    public function getNotifyUrl()
    {
        return $this->values['notifyUrl'];
    }

    /**
     * @param mixed $notifyUrl
     */
    public function setNotifyUrl($notifyUrl)
    {
        $this->values['notifyUrl'] = $notifyUrl;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->values['body'];
    }

    /**
     * @param mixed $body
     */
    public function setBody($body)
    {
        $this->values['body'] = $body;
    }

    /**
     * @return mixed
     */
    public function getDetail()
    {
        return $this->values['detail'];
    }

    /**
     * @param mixed $detail
     */
    public function setDetail($detail)
    {
        $this->values['detail'] = $detail;
    }

    /**
     * @return mixed
     */
    public function getClientIp()
    {
        return $this->values['clientIp'];
    }

    /**
     * @param mixed $clientIp
     */
    public function setClientIp($clientIp)
    {
        $this->values['clientIp'] = $clientIp;
    }

    /**
     * @return array
     */
    public function getAttach()
    {
        return json_decode($this->values['attach'], true);
    }

    /**
     * @param array $attach
     */
    public function setAttach($attach)
    {
        $this->values['attach'] = json_encode($attach, 320);
    }

    /**
     * @return mixed
     */
    public function getExtra()
    {
        return json_decode($this->values['extra'], true);
    }

    /**
     * @param array $extra
     */
    public function setExtra($extra)
    {
        $this->values['extra'] = json_encode($extra, 320);
    }

    /**
     * @return string
     */
    public function getIsNeedShare()
    {
        return $this->values['isNeedShare'];
    }

    /**
     * @param string $isNeedShare
     */
    public function setIsNeedShare($isNeedShare)
    {
        $this->values['isNeedShare'] = $isNeedShare;
    }


}

class QryOrderRequest extends ShengpayRequest
{
    protected function getApiResource()
    {
        return "/pay/queryOrder";
    }

    /**
     * @return mixed
     */
    public function getOutTradeNo()
    {
        return $this->values['outTradeNo'];
    }

    /**
     * @param mixed $outTradeNo
     */
    public function setOutTradeNo($outTradeNo)
    {
        $this->values['outTradeNo'] = $outTradeNo;
    }
}


class RefundRequest extends ShengpayRequest
{
    protected function getApiResource()
    {
        return "/refund/orderRefund";
    }

    /**
     * @return mixed
     */
    public function getOutRefundNo()
    {
        return $this->values['outRefundNo'];
    }

    /**
     * @param mixed $outRefundNo
     */
    public function setOutRefundNo($outRefundNo)
    {
        $this->values['outRefundNo'] = $outRefundNo;
    }

    /**
     * @return mixed
     */
    public function getOutTradeNo()
    {
        return $this->values['outTradeNo'];
    }

    /**
     * @param mixed $outTradeNo
     */
    public function setOutTradeNo($outTradeNo)
    {
        $this->values['outTradeNo'] = $outTradeNo;
    }

    /**
     * @return mixed
     */
    public function getRefundFee()
    {
        return $this->values['refundFee'];
    }

    /**
     * @param mixed $refundFee
     */
    public function setRefundFee($refundFee)
    {
        $this->values['refundFee'] = $refundFee;
    }

    /**
     * @return mixed
     */
    public function getRefundDesc()
    {
        return $this->values['refundDesc'];
    }

    /**
     * @param mixed $refundDesc
     */
    public function setRefundDesc($refundDesc)
    {
        $this->values['refundDesc'] = $refundDesc;
    }

    /**
     * @return mixed
     */
    public function getNotifyUrl()
    {
        return $this->values['notifyUrl'];
    }

    /**
     * @param mixed $notifyUrl
     */
    public function setNotifyUrl($notifyUrl)
    {
        $this->values['notifyUrl'] = $notifyUrl;
    }
}

class QryRefundOrderRequest extends ShengpayRequest
{
    protected function getApiResource()
    {
        return "/refund/queryRefundOrder";
    }

    /**
     * @return mixed
     */
    public function getOutRefundNo()
    {
        return $this->values['outRefundNo'];
    }

    /**
     * @param mixed $outRefundNo
     */
    public function setOutRefundNo($outRefundNo)
    {
        $this->values['outRefundNo'] = $outRefundNo;
    }
}