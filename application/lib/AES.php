<?php

namespace app\lib;

class AES
{
    static private $key;
    static public function main($key){
        self::$key = $key;
        return new self;
    }
    /**
     * @param string $string 需要加密的字符串
     * @param string $key 密钥
     * @return string
     */
    public static function encrypt($string)
    {
        $data = openssl_encrypt($string, 'AES-128-ECB', self::$key, OPENSSL_RAW_DATA);
        $data = base64_encode($data);
        return $data;
    }


    /**
     * @param $string
     * @param $key
     */
    public static function decrypt($string)
    {
        $decrypted = openssl_decrypt($string, 'AES-128-ECB', self::$key);

        return $decrypted;
    }

}