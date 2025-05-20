<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2019/7/1
 * Time: 15:09
 */

namespace app\lib;


class Bcex{
    static $apihost = 'https://api.bcex.online/api_market/';
    static $apikey = 'bd7973e03035a76f540d20fce3d46c8c';
    static $_instance;
    static public function main()
    {
        if (!isset(self::$_instance))
            self::$_instance = new self;
        return self::$_instance;
    }
    public function apikline($time_type,$market,$token,$limit){
        $data = [
            'time_type' => $time_type,
            'market' => $market,
            'token' => $token,
            'limit' => $limit
        ];
        $res = Curl::main()->url(self::$apihost.'apiKline')->data($data)->get();
        return json_decode($res,true);
    }
    public function tradelists(){
        $data = [
            'api_key' => self::$apikey
        ];
        $res = Curl::main()->url(self::$apihost.'getTradeLists')->data($data)->get();
        return json_decode($res,true);
    }
    public function latestTradeByPair($market,$token){
        $data = [
            'market' => $market,
            'token' => $token
        ];
        $res = Curl::main()->url(self::$apihost.'getLatestTradeByPair')->data($data)->get();
        return json_decode($res,true);
    }
    public function depth($market,$token){
        $data = [
            'market' => $market,
            'token' => $token
        ];
        $res = Curl::main()->url(self::$apihost.'market/depth')->data($data)->get();
        return json_decode($res,true);
    }
}