<?php
namespace app\index\controller;
use app\lib\AliSms;
use app\lib\Chuanglan;
use app\lib\Curl;
use app\lib\Util;
use OSS\OssClient;
use think\Controller;
use think\Db;
use think\facade\Config;

class Base extends Controller
{
    protected static $config;
    //未登录时候跳转到登录页面
    protected $is_jump_login = true;
    protected $member_id;
    protected $userinfo = false;
    protected $token = false;

    public function __construct()
    {
//        $num = round(5,20);
//        sleep($num);
        parent::__construct();
        self::$config = $this->setConfig();
        if ($this->is_jump_login) {
            $this->member_id = $this->checkLogin();
            if(!$this->member_id ){
                $this->response('请重新登录',false,-2);
            }
        }
    }
    protected function member_oplimit($time=3,$cache_key=false){
        if(!$cache_key){
            $cache_key = 'member_oplimit_'.$this->member_id.'_'.request()->action();
        }
        if(cache($cache_key)){
            
            $microtimeFloat = microtime(true);  
            $formattedTime = date('Y-m-d H:i:s.') . str_pad(round(($microtimeFloat - floor($microtimeFloat)) * 1000000), 6, '0', STR_PAD_LEFT);  
            $cache_keyx = Db::name("log")->insert([
                'msg' => $cache_key,
                'level' => '重复点击'.$formattedTime,
                'type' => '57'
            ]);
            
            $this->response('稍后再试',false,-5);
        }
        cache($cache_key,time(),$time);
    }
    /**
     * 设置配置信息
     * @access private
     * */
    private function setConfig(){
        return Db::name('config')->cache('web_conf',600)->column('val','key');
    }
    protected function verifyMemPay2(){
        $is_pay = Db::name('member_pay')->where('member_id',$this->member_id)->count();
        if(!$is_pay){
            $this->response('请先设置支付方式',false,-4);
        }
    }
    //支付密码
    protected function checkPaypwd($paypwd){
        if(!preg_match('/^\d{6}/',$paypwd)){
            return false;
        }
        if($this->userinfo['pay_pwd']){
            return md5($paypwd)==$this->userinfo['pay_pwd'];
        }else{
            return false;
        }
    }
    //授权验证
    protected final function checkLogin(){
        $auth = request()->header('x-requested-with');
        if($auth!='hongyun2023'){
            return false;
        }
        $token = request()->header('Authorization');
        if(!$token){
            return false;
        }
        $info = Db::name('member')->field('id,expire')->where('token',$token)->find();
        if(!$info){
            $member_id = cache('old_token_'.$token);
            if(!$member_id) {
                return false;
            }
        }else{
            if($info['expire']<time()){
                return false;
            }
            $member_id = $info['id'];
        }
        //验证成功
        $member_info = Db::name('member')->field('id,is_lock,pay_pwd,tel,level,parent_id,id_path,depth,update_time,is_vip,is_click')->where(['id'=>$member_id])->find();
        if(!$member_info || $member_info['is_lock']){
            return false;
        }
        $this->userinfo = $member_info;
        return $member_id;
    }

    /**
     * 退出登录
     * @return void
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    protected function logout(){
        if(!$this->member_id){
            $this->response('用户不在登录状态');
        }
        Db::name('member')->where(['id'=>$this->member_id])->update([
            'token'=>''
        ]);
        $this->response('退出成功',true);
    }


    /**
     * 返回数据 json
     * @param null $code
     * @param bool $status
     * @param array $result .
     * @param int $error_code
     */
    protected final function response($msg, $status = false, $code=null)
    {
        $data = ['status' => $status];
        if (isset($msg)) {
            $data['msg'] = $msg;
        }
        if($code){
            $data['code'] = $code;
        }else{
            $data['code'] = $status?200:-1;
        }
        json($data)->send();
        exit();
    }
    /**
    /**
     * 验证码验证是否正确
     * @param $tell
     * @param $code
     * @return
     */
    protected final function verifyCode($tell,$code,$flag=''){
        if(!preg_match('/^\d{6}$/',$code)){
            return [
                'status' => false,
                'msg' => '验证码有误'
            ];
        }
        $sessionKey = "verification_".$flag.$tell;
        $verification = cache($sessionKey);
        if (!$verification) {
            return [
                'status' => false,
                'msg' => '请先获取验证码'
            ];
        }
        if ($tell != $verification['tell']){
            return [
                'status' => false,
                'msg' => '验证码有误',
            ];
        }
        $auth_code = md5($code);//验证码
        if ($verification['verificat'] != $auth_code){
            return [
                'status' => false,
                'msg' => '验证码不正确'
            ];
        }
        if ($verification['expirat_time'] < time()) {
            return [
                'status' => false,
                'msg' => '请重新获取验证码'
            ];
        }
        return [
            'status' => true,
            'msg' => 'success'
        ];
    }
    /**
     * 获取验证码
     * @ajax
     * */
    protected final function sendMsg($tell,$flag='')
    {
        if(!preg_match('/^1[3-9]\d{9}$/',$tell)){
            return ['status'=>false,'msg'=>'手机号码有误'];
        }
        $tellTimeKey = "vercode_{$tell}_{$flag}";
        $sessionKey = "verification_".$flag.$tell;
        //验证码时效
        $verCodeAging = 300;
        //测试
//        cache($sessionKey,[
//            'tell' => $tell,
//            'expirat_time' => time() + $verCodeAging,
//            'verificat' => md5(888888),
//            'send_time' => time()
//        ],60);
//        return ['status'=>true,'msg'=>'success'];
        //
        if (cache($tellTimeKey)) {
            return ['status'=>false,'msg'=>'验证码获取过于频繁'];
        }
        //手机号限制
        $tellNumKey = 'verNumTell_' . $tell;
        $tellNum = cache($tellNumKey);
        if (!$tellNumKey) {
            $tellNum = 0;
        }
        $tellNum++;
        if ($tellNum > 30) {
            return ['status'=>false,'msg'=>'验证码获取频繁'];
        }
        $randInt = create_rand_str(6, 2);
        $ali_sms = config('ali_sms');
        $cl = new AliSms($ali_sms['account'], $ali_sms['password'],$ali_sms['signname'],$ali_sms['tcode']);
        $result = $cl->sendSMS($tell, $randInt);
        if ($result) {
            #发送成功
            cache($sessionKey,[
                'tell' => $tell,
                'expirat_time' => time() + $verCodeAging,
                'verificat' => md5($randInt),
                'send_time' => time()],300);
            //手机号限制时间
            cache($tellTimeKey, time(), 60);
            cache($tellNumKey, $tellNum, 3600);
            
            Db::name('log')->insert([
            'level' => '手机号获取验证码',
            'type' => '0',
            'msg' => '手机号:'.$tell.' | 验证码:'.$randInt.' |MD5:'.md5($randInt) //注释
            ]);
            
            return ['status'=>true,'msg'=>'success'];
        }
        return ['status'=>false,'msg'=>'验证码发送失败,请稍后再试'];
    }
    static protected function writeLog($type,$msg,$err_level='错误'){
        Db::name('log')->insert([
            'level' => $err_level,
            'type' => $type,
            'msg' => $msg,
        ]);
    }
    protected function create_order_sn($order_sn){
        $tmp = randStr(mt_rand(3,5)).'s'.$order_sn;
        return $tmp;
    }
    protected function ubwallet_get($url,$data=[],$token='',$method='get'){
        $headers['Accept'] = 'application/json';
        $headers['Content-Type'] = 'application/json';
        $headers['x-requested-with'] = 'bikuex';
        $headers['X-Requested-With'] = 'UB';
        $headers['Authorization'] = "Basic ".$token;
        $data = Curl::main()->header($headers)->data(json_encode($data))->url($url)->$method();
        return $data;
    }
    /**
     * 模拟post进行url请求
     * @param string $url
     * @param string $param
     */
    protected function request_post($url = '', $param = [], $headers = []) {
        if (empty($url)) {
            return false;
        }
        $headers[] = 'Accept: application/json';
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'x-requested-with:bikuex';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5000);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        $data = curl_exec($ch);

//        $i = 0;
//        while (isset($data['Message']) && $data['Message'] == "请求超时" && $i < 2 && @$data['status'] != 1) {
//            $i++;
//            $data = curl_exec($ch);
//        }
        curl_close($ch);
        $return = json_decode($data, true);
        return $return;
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
    protected function batch_ucheck($mobile){
        $exist = Db::name('mobile_check')->field('status')->where('mobile',$mobile)->find();
        if(!$exist){
            $ip = get_client_ip();
            if(!$ip){
                return false;
            }else{
                $cl = new Chuanglan();
                $data = $cl->wcheck($mobile,get_client_ip());
                if($data){
                    Db::name('mobile_check')->insert([
                        'ip' => $ip,
                        'mobile' => $data['mobile'],
                        'status' => $data['status']
                    ]);
                    return $data['status'] != 'B1';
                }else{
                    return false;
                }
            }
        }else{
            return $exist['status'] != 'B1';
        }
    }
}