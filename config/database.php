<?php
if(is_dev_server()){
    //测试数据库
    return [
        // 数据库调试模式
        'debug' => false,
        // 数据库类型
        'type' => 'mysql',
        // 服务器地址
        'hostname' => '1.95.78.116',
        // 数据库名
        'database' => 'hongyun_cqxjr_cn',
        // 用户名
        'username' => 'hongyun_cqxjr_cn',
        // 密码
        'password' => 'rfQTxzjdp1691QA1',
        // 编码
        'charset' => 'utf8mb4',
        // 端口
        'hostport' => '3306',
        // 主从
        'deploy' => 0,
        // 分离
        'rw_separate' => false,
    ];
}else{
    //正式数据库
    return [
        // 数据库调试模式
        'debug' => false,
        // 数据库类型
        'type' => 'mysql',
        // 服务器地址
        'hostname' => '127.0.0.1',
        // 数据库名
        'database' => 'hongyun',
        // 用户名
        'username' => 'hongyun',
        // 密码
        'password' => 'Gm336YXFmt6XpiRY',
        // 编码
        'charset' => 'utf8mb4',
        // 端口
        'hostport' => '3306',
        // 主从
        'deploy' => 0,
        // 分离
        'rw_separate' => false,
    ];
}

