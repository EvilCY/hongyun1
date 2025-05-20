<?php
/**
* 	配置信息
*/

abstract class ShengPayConfigInterface
{
    /**
     * 是否线上环境
     * @return mixed
     */
    public abstract function isLive();
    public abstract function getNotifyUrl();
    public abstract function getSdpAppId();
    public abstract function getMchId();
	public abstract function getSignType();
	public abstract function getSdpPublicKey();
    public abstract function getMchPrivateKey();
}
