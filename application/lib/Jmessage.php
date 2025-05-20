<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2020/12/31
 * Time: 14:16
 */

namespace app\lib;
use JMessage\IM\User;
use JMessage\IM\Resource;

require 'jmessage/autoload.php';

class Jmessage
{
    private static $client;
    public function __construct()
    {
        $appKey = '59ca53d637d17017dd1cb4f2';
        $masterSecret = 'f377d095c937320975a348d7';

        self::$client = new \JMessage\JMessage($appKey, $masterSecret);
    }
    public function getAuth(){
       return self::$client->getAuth();
    }
    public function disableSsl() {
        return self::$client->disableSsl();
    }
    public function reg($username, $password,$nickname){
        $user = new User(self::$client);
        return $this->response($user->register($username, $password,$nickname));
    }
    public function show($username){
        $user = new User(self::$client);
        return $this->response($user->show($username));
    }
    private function response($data){
        return [
            'code' => $data['http_code'],
            'msg' => $data['body']
        ];
    }
}