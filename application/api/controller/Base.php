<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2020/5/1
 * Time: 11:02
 */

namespace app\api\controller;
use think\Controller;
use think\Db;
use think\facade\Config;

set_time_limit(0);

class Base extends Controller
{
    static protected $config;
    public function __construct()
    {
        parent::__construct();
        $ip = request()->ip();
        if($ip != '121.41.28.252'){
            abort('404');
        }
        self::$config = $this->setConfig();
    }
    private function setConfig()
    {
        return Db::name('config')->cache('web_conf',600)->column('val','key');
    }

    static protected function crate_rand_str($length=10,$type=true){
        $str = '0123456789';
        if($type) $str = 'QWERTYUIOPASDFGHJKLZXCVBNM0123456789mnbvcxzlkjhgfdsapoiuytrewq';
        $randString = '';
        $len = strlen($str) - 1;
        for ($i = 0; $i < $length; $i ++)
        {
            $num = mt_rand(0, $len);
            $randString .= $str[$num];
        }
        return $randString;
    }
    static protected function writeLog($type,$msg,$err_level='错误'){
        Db::name('log')->insert([
            'level' => $err_level,
            'type' => $type,
            'msg' => $msg,
        ]);
    }
}