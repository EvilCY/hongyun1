<?php
namespace think;
require __DIR__ . '/../thinkphp/base.php';
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods:POST,GET');//表示只允许POST请求
header('Access-Control-Allow-Credentials:true');
header('Access-Control-Allow-Headers:x-requested-with,content-type,hy-token,Authorization');
Container::get('app')->run()->send();