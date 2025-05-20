<?php
namespace app\lib\SanDe\api;
class Common
{
    // 请求报文体
    public $body;
    /*
    |--------------------------------------------------------------------------
    | step1.组装参数
    |--------------------------------------------------------------------------
    */
    
    // 参数
    public function postData($method)
    {
        if(!empty($_COOKIE['config'])){
            $config =  unserialize($_COOKIE['config']) ;
        } 
        if(empty($config)){
            $config = include('Basics.php');
            $config = $config['variable'];
        }
        
        $data = array(
            'head' => array(
                'version'     => '1.0',
                'method'      => $method,
                'productId'   => $config['productId'],
                'accessType'  => $config['accessType'],
                'mid'         => $config['mid'],
                'plMid'       => $config['plMid'],
                'channelType' => $config['channelType'],
                'reqTime'     => date('YmdHis', time()),
            ),
            'body' => $this->body,
        );

        $postData = array(
            'charset'  => 'utf-8',
            'signType' => '01',
            'data'     => json_encode($data),
            'sign'     => $this->sign($data),
        );
        return $postData;
    }

    // 参数映射 继承类需要完善这个方法
    protected function apiMap()
    {
        return array();
    }

    /*
    |--------------------------------------------------------------------------
    | step2. 请求
    |--------------------------------------------------------------------------
    */
    // curl请求接口
    public function request($apiName)
    {
        $config = include('Basics.php');
        $apiMap = $this->apiMap();
        if (!isset($apiMap[$apiName])) {
            throw new \Exception('接口名错误');
        }
        $url      = $config['apiUrl'] . $apiMap[$apiName]['url'];

        $postData = $this->postData($apiMap[$apiName]['method']);
        
        
        $ret    = $this->httpPost($url, $postData);

        $retAry = $this->parseResult($ret);

        return $retAry;
    }

    // curl. 发送请求
    public function httpPost($url, $params)
    {
        if (empty($url) || empty($params)) {
            throw new \Exception('请求参数错误');
        }
        $params = http_build_query($params);
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $data  = curl_exec($ch);
            $err   = curl_error($ch);
            $errno = curl_errno($ch);
            if ($errno) {
                $msg = 'curl errInfo: ' . $err . ' curl errNo: ' . $errno;
                throw new \Exception($msg);
            }
            curl_close($ch);
            return $data;
        } catch (\Exception $e) {
            if ($ch) curl_close($ch);
            throw $e;
        }
    }
    // curl.解析返回数据
    protected function parseResult($result)
    {
        $arr      = array();
        $response = urldecode($result);
        $arrStr   = explode('&', $response);
        foreach ($arrStr as $str) {
            $p         = strpos($str, "=");
            $key       = substr($str, 0, $p);
            $value     = substr($str, $p + 1);
            $arr[$key] = $value;
        }

        return $arr;
    }
    
    // 表单请求接口
    public function form($apiName)
    {
        $config = include('Basics.php');
        $apiMap = $this->apiMap();
        if (!isset($apiMap[$apiName])) {
            throw new \Exception('接口名错误');
        }
        $postData = $this->postData($apiMap[$apiName]['method']);
        $url      = $config['apiUrl'] . $apiMap[$apiName]['url'];

        $form = '<form action="' . $url . '" method="post">';
        foreach ($postData as $k => $v) {
            $form .= "{$k} <p><input type='text' name='{$k}' value='{$v}'></p>";
        }
        $form .= '<input type="submit" value="提交"></form>';
        return ['form'=>$form,'postData'=>$postData];
    }
    /*
    |--------------------------------------------------------------------------
    | step3.签名 + 验签
    |--------------------------------------------------------------------------
    */

    // 公钥
    private function publicKey()
    {
        try {
            $config = include('Basics.php');
            $file = file_get_contents($config['publicKeyPath']);
            if (!$file) {
                throw new \Exception('getPublicKey::file_get_contents ERROR 公钥文件读取有误,config文件夹中进行修改');
            }
            $cert   = chunk_split(base64_encode($file), 64, "\n");
            $cert   = "-----BEGIN CERTIFICATE-----\n" . $cert . "-----END CERTIFICATE-----\n";
            $res    = openssl_pkey_get_public($cert);
            $detail = openssl_pkey_get_details($res);
            openssl_free_key($res);
            if (!$detail) {
                throw new \Exception('getPublicKey::openssl_pkey_get_details ERROR 公钥文件解析有误');
            }
            return $detail['key'];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 私钥
    private function privateKey()
    {
        try {
            $config = include('Basics.php');
            $file = file_get_contents($config['privateKeyPath']);
            if (!$file) {
                throw new \Exception('getPrivateKey::file_get_contents 私钥文件读取有误,config文件夹中进行修改');
            }
            if (!openssl_pkcs12_read($file, $cert, $config['privateKeyPwd'])) {
                throw new \Exception('getPrivateKey::openssl_pkcs12_read ERROR 私钥密码错误，config文件夹中进行修改');
            }
            return $cert['pkey'];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 私钥加签
    protected function sign($plainText)
    {
        $plainText = json_encode($plainText);
        try {
            $resource = openssl_pkey_get_private($this->privateKey());
            $result   = openssl_sign($plainText, $sign, $resource);
            openssl_free_key($resource);
            if (!$result) throw new \Exception('sign error');
            return base64_encode($sign);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    // 公钥验签
    public function verify($plainText, $sign)
    {
        $resource = openssl_pkey_get_public($this->publicKey());
        $result   = openssl_verify($plainText, base64_decode($sign), $resource);
        openssl_free_key($resource);

        if (!$result) {
            throw new \Exception('签名验证未通过,plainText:' . $plainText . '。sign:' . $sign);
        }
        return $result;
    }
}
