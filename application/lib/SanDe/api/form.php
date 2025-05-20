<?php
$method = $_GET['method'];
$type = $_GET['type'];
function getdata($action){
    $helper = include('../helper/Params.php');
    if(empty($_COOKIE['params'])){
        $newvalue = '';
    }else{
        $newvalue = unserialize($_COOKIE['params']);
    }
    if(empty($_COOKIE['config'])){
        $config = include('../config/Basics.php');
        $config = $config['variable'];
    }else{
        $config = unserialize($_COOKIE['config']);
    }
    $configform = '<form id="configform" method="post">';  
    foreach ($config as $k => $v) {
        $configform .= "<p>{$k}<input type='text' name='{$k}' value='{$v}'></p>";
    }
    $configform .= '</form>';  
    $form = '<fieldset style="width:15%;" class="myCode"><legend>入参配置</legend><form style="font-size: 14px;" id="ruform" method="post">';
    foreach ($helper['body'][$action] as $k => $v) {
        $value = isset($newvalue[$v['param']]) ? $newvalue[$v['param']] : $v['value'];
        $font = $v['require']=='true'?"<font color='red'>*</font>":"";
        $form .= "<p>{$font}{$v['param']} ({$v['name']})<input maxlength='{$v['lengthmax']}' style='margin-top:5px' type='text' name='{$v['param']}' value='{$value}'></p>";
    }
    $form .= '<input  type="button" onclick="rusub()" value="保存"></form></fieldset>';
    return json_encode([
        'form'   => $form ,
        'configform'   => $configform ,
        'config'   => $config ,
    ]);
};

function setdata($action){
    $params = $_GET['params'];
    $params = explode('&',$params);
    $config = $_GET['config'];
    $config = explode('&',$config);
    $arr=[];
    foreach($params as $v){
        $v = explode('=',$v);
        $arr[$v[0]] = urldecode($v[1]);  
    }
    $arr2=[];
    foreach($config as $v){
        $v = explode('=',$v);
        $arr2[$v[0]] = urldecode($v[1]);  
    }
    //var_dump($arr2);
    setcookie("params", serialize($arr), time()+60*5);
    setcookie("config", serialize($arr2), time()+60*5);
    return json_encode($arr);
}
function deletecookie($action){
    setcookie("params", '',time()-1);
    setcookie("config", '',time()- 1);
}

echo $type($method);

?>





