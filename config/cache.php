<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2019/7/25
 * Time: 13:45
 */
if(is_dev_server()){
    return [
        'type' => 'Redis',
        'host' => '127.0.0.1',
        'port' => '6379',
        'prefix' => 'hongyun_',
        'expire' => 0
    ];
}else{
    return [
        'type' => 'Redis',
        'host' => '127.0.0.1',
        'port' => '6379',
        'password' => '',
        'select' => 3,
        'prefix' => 'hongyun_',
        'expire' => 0
    ];
}
