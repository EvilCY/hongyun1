<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2019/9/26
 * Time: 22:34
 */

namespace app\index\controller;


use think\Db;

class Address extends Base
{
    
    /**
	 * 解析IP
	 * @param $ip
	 * @return mixed
	 */
	private function ipContent($ip)
	{
		$url = 'https://opendata.baidu.com/api.php?query='.$ip.'&co=&resource_id=6006&oe=utf8';
		$ch = curl_init();
		//设置选项，包括URL
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		//执行并获取HTML文档内容
		$output = curl_exec($ch);
		//释放curl句柄
		curl_close($ch);
		$result = json_decode($output, true);
		return $result;
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
    
    //地址添加
    public function add(){
        $data = input('post.');
        
        if(!preg_match('/^1[3-9]\d{9}$/',$data['tel'])){
            $this->response('手机号有误');
        }
        
        $ip = $this->get_real_ip();
		$result = $this->ipContent($ip);
        $address_ip = $result['data'][0]['location'].':'.$result['data'][0]['origip'];
        self::writeLog(101,'新增地址:'.$this->member_id.' | '.$data['uname'].' | '.$data['tel'].' | '.$data['address'].$data['address_sub'],$address_ip);
        
        $data['is_default'] = input('post.is_default',false);
        $nums = Db::name('mall_address')->where(['member_id'=>$this->member_id])->count('id');
        if($data['is_default'] && $nums>0){
            Db::name('mall_address')->where(['member_id'=>$this->member_id])->update([
                'is_default' => 0
            ]);
        }
        $data['member_id'] = $this->member_id;
        if($nums==0){
            $data['is_default'] = 1;
        }else{
            $data['is_default'] = $data['is_default']?1:0;
        }
        $id = Db::name('mall_address')->insertGetId($data);
        if($id){
            $this->response($id,true);
        }else{
            $this->response('添加失败');
        }
    }
    //我的详细地址
    public function my_address(){
        $address_id = input('address_id');
        if($address_id){
            $info = Db::name('mall_address')->field('id,tel,uname,address,address_sub,is_default')->where(['id'=>$address_id])->find();
        }else{
            $info = Db::name('mall_address')->field('id,tel,uname,address,address_sub,is_default')->where(['member_id'=>$this->member_id])->order('is_default desc')->find();
        }
        if(!$info){
            $this->response('暂无收货地址');
        }else{
            $this->response($info,true);
        }
    }
    //我的地址列表
    /*
    public function address_list(){
        $list = Db::name('mall_address')->field('id,tel,uname,address,address_sub,is_default')->where(['member_id'=>$this->member_id])->order('is_default desc,id desc')->select();
        if($list){
            $this->response($list,true);
        }else{
            $this->response('暂无收货地址');
        }
    }
    */
    public function address_list(){
        $list = Db::name('mall_address')->field('id,tel,uname,address,address_sub,is_default')->where(['member_id'=>$this->member_id])->order('is_default desc,id desc')->select();
        if($list){
        // 遍历查询结果，并清理address_sub字段
        foreach ($list as &$item) {
            // 使用正则表达式只保留中文、数字、空格和英文字符
            $item['address_sub'] = preg_replace('/[^\p{Han}\da-zA-Z\s]/u', '', $item['address_sub']);
        }
            $this->response($list, true);
        } else {
            $this->response('暂无收货地址');
        }
    }
    
    //删除收货地址
    public function address_del(){
        $id = input('post.address_id');
        if(!$id){
            $this->response('系统繁忙');
        }
        $info = Db::name('mall_address')->field('is_default')->where(['id'=>$id,'member_id'=>$this->member_id])->findOrFail();
        if(!$info){
            $this->response('该地址已删除');
        }
        if($info['is_default']){
            $new_id = Db::name('mall_address')->where(['member_id'=>$this->member_id])->order('id')->value('id');
            if($new_id){
                Db::name('mall_address')->where(['id'=>$new_id])->update([
                    'is_default' => 1
                ]);
            }
        }
        Db::name('mall_address')->where(['id'=>$id])->delete();
        $this->response('操作成功',true);
    }
    public function address_edit(){
        $data = input('post.');
        $address_id = $data['id'];
        
        if(!preg_match('/^1[3-9]\d{9}$/',$data['tel'])){
            $this->response('手机号有误');
        }
        
        $ip = $this->get_real_ip();
		$result = $this->ipContent($ip);
        $address_ip = $result['data'][0]['location'].':'.$result['data'][0]['origip'];
        self::writeLog(101,'修改地址:'.$this->member_id.' | '.$data['uname'].' | '.$data['tel'].' | '.$data['address'].$data['address_sub'],$address_ip);
        
        $info = Db::name('mall_address')->field('is_default')->where(['member_id'=>$this->member_id,'id'=>$address_id])->findOrFail();
        $data['is_default'] = $data['is_default']?1:0;
        if(!$info['is_default'] && $data['is_default']==1){
            Db::name('mall_address')->where(['member_id'=>$this->member_id])->update([
                'is_default' => 0
            ]);
        }
        $res = Db::name('mall_address')->where(['id'=>$address_id])->update([
            'uname' => $data['uname'],
            'tel' => $data['tel'],
            'address' => $data['address'],
            'address_sub' => $data['address_sub'],
            'is_default' =>  $data['is_default']
        ]);
        if($res){
            $this->response('修改成功',true);
        }else{
            $this->response('未作任何修改');
        }
    }
}