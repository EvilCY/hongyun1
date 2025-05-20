<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2020/8/5
 * Time: 15:57
 */

namespace app\lib;


class Util
{
    public function decode_ewm($ewm_url){
        $appcode = 'e74b91c350a24e17b19c07f6f19caa0b';
        $host = "http://qrapi.market.alicloudapi.com/yunapi/qrdecode.html";
        $res = Curl::main()->data(['imgurl'=>$ewm_url])->header(['Content-Type'=>'application/x-www-form-urlencoded; charset=UTF-8','Authorization'=>"APPCODE " . $appcode])->url($host)->post();
        $res = json_decode($res,true);
        if(@$res['status']==1 && @$res['data']['raw_type']=="QR-Code"){
            $url_data = explode('?',strtolower($res['data']['raw_text']));
            return $url_data[0];
        }else{
            return false;
        }
    }
    public function verifybankcard($uname,$idCard,$accountNo,&$msg){
        $appcode = 'e74b91c350a24e17b19c07f6f19caa0b';
        $host = 'https://bcard3and4.market.alicloudapi.com/bank3CheckNew';
        $data = [
            'accountNo' => $accountNo,
            'name' => $uname,
            'idCard' => $idCard
        ];
        $res = Curl::main()->data($data)->header(['Authorization'=>"APPCODE " . $appcode])->url($host)->get();
        $res = json_decode($res,true);
        if(@$res['status']==01){
            $msg = $res['msg'];
            return true;
        }else{
            $msg = $res['msg'];
            return false;
        }
    }
    public function asciiSignature($str,$timestamp,$type){
        $ascii = ord($str);
        var_dump($ascii);
//        $type = $ascii%5;
        $salt = substr($str,-1,5);
        $signstr = $salt.$str.$timestamp;
        $signstr_2 = $str.$timestamp.$salt;
        if($type==0){
           return md5($signstr);
        }else if($type==1){
            return md5($signstr_2);
        }else if($type==2){
            return md5(AES::main($salt)->encrypt($signstr));
        }else if($type==3){
            return md5(AES::main($salt)->encrypt($signstr_2));
        }else if($type==4){
            return sha1($signstr);
        }else{
            return sha1($signstr_2);
        }
    }
}