<?php


class ShengPayConfig extends ShengPayConfigInterface
{

    private $privateKey;
    private $publicKey;

    function __construct()
    {
        $this->privateKey = file_get_contents("../application/lib/shengpay/config/rsa_private_key.pem");
        $this->publicKey = file_get_contents("../application/lib/shengpay/config/rsa_public_key.pem");
    }

    public function isLive()
    {
        return false;
    }

    public function getNotifyUrl()
    {
        return "notify";
    }

    public function getSdpAppId()
    {
        return "456416321249738752";
    }

    public function getMchId()
    {
        return "88010055";
    }

    public function getSignType()
    {
        return "RSA";
    }

    public function getSdpPublicKey()
    {
        return $this->publicKey;
    }

    public function getMchPrivateKey()
    {
        return $this->privateKey;
    }
}