<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2020/6/9
 * Time: 13:58
 */

namespace app\lib;

class Shandw
{
    const HOST = 'http://www.shandw.com/auth';
    const CHANNEL = 13725;
    const SECRET = '695cca727770444d9f856e4f97855666';
    public function getAuthUrl($openid,$nick,$avatar,$phone){
        $params = [
            'channel' => self::CHANNEL,
            'openid' => $openid,
            'nick' => $nick,
            'avatar' => $avatar,
            'sex' => 0,
            'phone' => $phone,
            'time' => time(),
        ];
        $params['sign'] = $this->sign($params,self::SECRET);
        $url = self::HOST.'?sdw_dl=1&sdw_simple=10005&sdw_ld=17sdw_kf=1&'.http_build_query($params);
        return $url;
    }
    private function sign($params,$secret){
        $str = "channel={$params['channel']}&openid={$params['openid']}&time={$params['time']}&nick={$params['nick']}&avatar={$params['avatar']}&sex={$params['sex']}&phone={$params['phone']}";
        $str.=$secret;
        return strtolower(md5($str));
    }
}