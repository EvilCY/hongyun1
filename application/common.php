<?php
if(!function_exists('is_dev_server')){
    //检测是否是测试服
    function is_dev_server(){
        return in_array($_SERVER['HTTP_HOST'],[
//            'hongyun.cqxjr.cn',
            'hongyun.com',
            'test.gxqhydf520.shop',
        ]);
    }
}
//生成唯一token
function generate_token(){
    $charid = strtoupper(md5(uniqid(mt_rand(), true)));
    return substr($charid, 0, 8) . substr($charid, 8, 4) . substr($charid, 12, 4) . substr($charid, 16, 4) . substr($charid, 20, 12);
}
function trade_status($mid){
    $cache_key ='trade_lock_'.$mid;
    $the_time = cache($cache_key);
    if($the_time){
        $remark = \think\Db::name('trade_lock_log')->where('member_id',$mid)->order('id desc')->value('remark');
        return '备注：'.$remark.'<br/>解锁时间：'.date('m-d H:i',$the_time);
    }else{
        return ' ';
    }
}
/**
 * # php显示几分钟前，几小前，昨天，前天，多少天前的函数
 * @param $posttime 格式化后的时间
 */
function formateTimeAgo($posttime)
{
    $nowtimes = strtotime(date('Y-m-d H:i:s'),time());
    $counttime = $nowtimes - strtotime($posttime);
    if($counttime<=60){
        return '刚刚';
    }else if($counttime>60 && $counttime<=120){
        return '1分钟前';
    }else if($counttime>120 && $counttime<=180){
        return '2分钟前';
    }else if($counttime>180 && $counttime<3600){
        return intval(($counttime/60)).'分钟前';
    }else if($counttime>=3600 && $counttime<3600*24){
        return intval(($counttime/3600)).'小时前';
    }else if($counttime>=3600*24 && $counttime<3600*24*2){
        return '昨天';
    }else if($counttime>=3600*24*2 && $counttime<3600*24*3){
        return '前天';
    }else if($counttime>=3600*24*3 && $counttime<=3600*24*7){
        return intval(($counttime/(3600*24))).'天前';
    }else{
        return date('Y-m-d', strtotime($posttime));
    }
}
/**
 * 将制定数组链接为指定字符串
 * 如 array(key=>val,key1=>val2) 结果为：key=val&key1=val2
 * @param string $str 链接字符串1
 * @param string $str1 链接字符串2
 * @param array $arr 拼接数组
 * @return string
 * */
function joins($arr,$str = '=',$str1 = '&'){
    if(!is_array($arr))
        return false;
    $new_arr = array();

    foreach($arr as $k=>$v){
        $new_arr[] = "{$k}{$str}{$v}";
    }
    return join($str1,$new_arr);
}
/**
 * @param int $length
 * @param bool $type
 * @return string
 */
function create_rand_str($length=10,$type=1){
    switch ($type){
        case 1:$str = 'QWERTYUIOPASDFGHJKLZXCVBNM0123456789mnbvcxzlkjhgfdsapoiuytrewq';break;
        case 2:$str = '0123456789';break;
        case 3:$str = 'QWERTYUIOPASDFGHJKLZXCVBNMmnbvcxzlkjhgfdsapoiuytrewq';break;
        default:return '';
    }
    $randString = '';
    $len = strlen($str) - 1;
    for ($i = 0; $i < $length; $i ++)
    {
        $num = mt_rand(0, $len);
        $randString .= $str[$num];
    }
    return $randString;
}
/*
 * @ 生成随机数字
 * @ param int $length 需要生成的长度
 * @ param int $type 1：使用数字生成，2：使用数字加字母组成
 * @ return 字符串
 * */
function GetStr($length,$type = 0)
{
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
/**
 * 获取客户端IP地址，不能伪装
 * @return mixed
 */
function get_client_ip($md5=false)
{
    if (isset($_SERVER['HTTP_X_REAL_FORWARDED_FOR'])) {
        $ip =  $_SERVER['HTTP_X_REAL_FORWARDED_FOR'];
    }else if(isset($_SERVER['HTTP_X_CONNECTING_IP'])) {
        $ip =  $_SERVER['HTTP_X_CONNECTING_IP'];
    }else if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_str =  $_SERVER['HTTP_X_FORWARDED_FOR'];
        $ip = explode(',',$ip_str)[0];
    }else{
        $ip =  $_SERVER['REMOTE_ADDR'];
    }
    if($md5){
        return md5($ip);
    }
    return $ip;
}
function getAgeByID($id){

//过了这年的生日才算多了1周岁
    if(empty($id)) return '';
    $date=strtotime(substr($id,6,8));
//获得出生年月日的时间戳
    $today=strtotime('today');
//获得今日的时间戳
    $diff=floor(($today-$date)/86400/365);
//得到两个日期相差的大体年数

//strtotime加上这个年数后得到那日的时间戳后与今日的时间戳相比
    $age=strtotime(substr($id,6,8).' +'.$diff.'years')>$today?($diff+1):$diff;

    return $age;
}
function float_number($number){
    $length = strlen($number);  //数字长度
    if($length > 8){ //亿单位
        $str = substr_replace(floor($number * 0.0000001),'.',-1,0)."亿";
    }elseif($length >4){ //万单位
        //截取前俩为
        $str = floor($number * 0.001) * 0.1."万";

    }else{
        return $number;
    }
    return $str;
}
function writeLog($type,$msg,$err_level='错误'){
    \think\Db::name('log')->insert([
        'level' => $err_level,
        'type' => $type,
        'msg' => $msg,
    ]);
}
function writeLog_s($s_member_id,$s_type,$s_num,$s_desc){
    $s_info = Db::name('merits_record')->where([
        'member_id' => $s_member_id,
        'type' => $s_type,
        'num' => $s_num,
        'desc' => $s_desc,
        'create_time' => date('Y-m-d'),
    ])->find();
    if ($s_info){
    //存在数据
    }else{
    Db::name('merits_record')->insert([
        'member_id' => $s_member_id,
        'type' => $s_type,
        'num' => $s_num,
        'desc' => $s_desc,
        'create_time' => date('Y-m-d'),
    ]);
    }
}
function timeMicro(){
    list($s1, $s2) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
}
function ip_in_network($ip, $networks)
{
    $ip = (double)(sprintf("%u", ip2long($ip)));
    foreach ($networks as $network){
        $s = explode('/', $network);
        $network_start = (double)(sprintf("%u", ip2long($s[0])));
        $network_len = pow(2, 32 - $s[1]);
        $network_end = $network_start + $network_len - 1;
        if ($ip >= $network_start && $ip <= $network_end) {
            return true;
            break;
        }
    }
    return false;
}
/**
 * 金额格式化
 */
function amount_format($price, $decimals = 2)
{
    return number_format($price, $decimals, '.', '');
}

function mosaicMobileNum($mobile) {
	return preg_replace('/(\d{3})\d{4}(\d{4})/', '$1****$2', $mobile);
}
function mosaicTrade($mobile){
//    return preg_replace("/(\d{3})(\d{8})/","$1********",$mobile);
    return '***********';
}
function mosaicIdCard($idcard){
    return preg_replace('/(\d{6})\d{8}(\d{4})/', '$1********$2', $idcard);

}
function getMemTel($member_id){
    if(!$member_id){
        return '';
    }
    return \think\Db::name('member')->cache(600)->where(['id'=>$member_id])->value('tel').'';
}
function randomMobile()
{
    $tel_arr = array(
        '130','131','132','133','134','135','136','137','138','139','144','147','150','151','152','153','155','156','157','158','159','176','177','178','180','181','182','183','184','185','186','187','188','189',
    );
    return $tel_arr[array_rand($tel_arr)].mt_rand(1000,9999).mt_rand(1000,9999);
}
function diffBetweenTwoDays($day1, $day2){
    $second1 = strtotime($day1);
    $second2 = strtotime($day2);

    if ($second1 < $second2) {
        $tmp = $second2;
        $second2 = $second1;
        $second1 = $tmp;
    }
    return ($second1 - $second2) / 86400;
}
/**
 * 解析|生成URL地址
 * @param string|array $data url地址参数2种写法：['p'=>1] | 'p=2'
 * @param string $global_url 解析目的地址，默认当前页面地址 格式：http://xx.com/helloword?do=hello
 * @return string 返回最终结果url
 * */
function url_diy($data = null, $global_url = '')
{
    if (empty($global_url))
        $global_url = $_SERVER['REQUEST_URI'];
    $parse_url = parse_url($global_url);
    $url = '';
    if (isset($parse_url['scheme']))
        $url .= $parse_url['scheme'] . '://' . $parse_url['host'];
    $url .= $parse_url['path'];
    if (!isset($parse_url['query']) and !isset($data))
        return $url;
    parse_str($parse_url['query'], $query);
    if (isset($data)) {
        if (is_string($data))
            parse_str($data, $data);
        $query = array_merge($query, $data);
    }
    $url .= '?' . http_build_query($query);
    return $url;
}
function gbkToUtf8($param)
{
    $conversions = function ($str) {
        return mb_convert_encoding($str, 'utf-8', 'gbk');
    };
    if (is_array($param)) {
        $res = [];
        foreach ($param as $k => $v) {
            $res[$k] = $conversions($v);
        }
        return $res;
    }
    return $conversions($param);
}

/**
 * 提示框
 * @param string $string
 * @param string $url
 * @return void
 */
function jsAlter($string, $url = '')
{
    echo '<script type="text/javascript" charset="UTF-8">document.title = "' . $string . '";alert("' . $string . '");' . ($url ? 'window.location = "' . $url . '";' : '') . '</script>';
}

/**
 * @param int $length
 * @param bool $type
 * @return string
 */
function crate_rand_str($length=10,$type=true){
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
function checkIdCard($id_card){
    if(!$id_card){
        return false;
    }
    $re = '/(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/';
	if(!preg_match($re,$id_card)){
        return false;
    }
	return true;
}
function generateToken(){
    $charid = strtoupper(md5(uniqid(mt_rand(), true)));
    return substr($charid, 0, 8) . substr($charid, 8, 4) . substr($charid, 12, 4) . substr($charid, 16, 4) . substr($charid, 20, 12);
}
/**
 * 随机产生字符串
 * @param int $length 产生随机数长度
 * @param bool $int 是否产生数字
 * @param bool $lowercase 是否小写
 * @param bool $capital 是否大写
 * @return string
 * */
function randStr($length = 1, $int = true, $lowercase = true, $capital = true)
{

    $randPar = array(
        'number' => '1234567890',
        'character' => 'qwertyuiopadfghjklzxcvbnm'
    );
    $str = '';
    if ($int) {//数字
        $str .= $randPar['number'];
    }
    if ($lowercase) {//小写
        $str .= $randPar['character'];
    }
    if ($capital) {//大写
        $str .= strtoupper($randPar['character']);
    }
    $str = str_shuffle($str);
    return substr($str, 0, $length);
}
function checkPwd($pwd)
{
    return (bool) preg_match('/^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z_]{6,16}$/',$pwd);
}
function isMobile()
{
    return (bool)preg_match('/AppleWebKit.*Mobile.*/i', $_SERVER['HTTP_USER_AGENT']);
}
/**
 * 加密哈希密码
 * @param string $pwd
 * @return string
 */
function hashPwd($pwd = '')
{
    return md5(sha1($pwd) . 'ADDAGUIDQYQYEQ8123621831737215487////11223321223121');
}
/**
 * 计算加密登陆SID
 * @param string $tell
 * @return string
 */
function hashSid($tell)
{
    return md5(uniqid($tell . 'ADDAGUIDQYQYEQ8123621831737215487////11223321223121'));
}
//function encrypt($string, $operation = 'E', $key = '')
//{
//    $key = $key ?: config('encode_key');
//    $key = md5($key);
//    $key_length = strlen($key);
//    if ($operation == 'D') {
//        $string = str_ireplace(' ', '+', $string);
//    }
//    $string = $operation == 'D' ? base64_decode($string) : substr(md5($string . $key), 0, 8) . $string;
//    $string_length = strlen($string);
//    $rndkey = $box = array();
//    $result = '';
//    for ($i = 0; $i <= 255; $i++) {
//        $rndkey[$i] = ord($key[$i % $key_length]);
//        $box[$i] = $i;
//    }
//    for ($j = $i = 0; $i < 256; $i++) {
//        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
//        $tmp = $box[$i];
//        $box[$i] = $box[$j];
//        $box[$j] = $tmp;
//    }
//    for ($a = $j = $i = 0; $i < $string_length; $i++) {
//        $a = ($a + 1) % 256;
//        $j = ($j + $box[$a]) % 256;
//        $tmp = $box[$a];
//        $box[$a] = $box[$j];
//        $box[$j] = $tmp;
//        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
//    }
//    if ($operation == 'D') {
//        if (substr($result, 0, 8) == substr(md5(substr($result, 8) . $key), 0, 8)) {
//            return substr($result, 8);
//        } else {
//            return '';
//        }
//    } else {
//        return str_replace('=', '', base64_encode($result));
//    }
//}

