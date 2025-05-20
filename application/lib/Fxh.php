<?php
/**
 * 非小号行情接口
 * User: Angerl
 * Date: 2020/8/9
 * Time: 11:56
 */

namespace app\lib;


class Fxh
{
    const APIHOST = 'https://fxhapi.feixiaohao.com/public/v1/ticker/';
    public function market_price(){
       $data =  Curl::main()->url(self::APIHOST)->get();
       return $data;
    }
}