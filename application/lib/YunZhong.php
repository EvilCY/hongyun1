<?php
/**
 * Created by PhpStorm.
 * User: L
 * Date: 2023/05/13
 * Time: 15:13
 */

namespace app\lib;
use think\Db;

class YunZhong
{
    private $userName;//账号
    private $password;//密码
    private $enterpriseId = 'E21255938';//企业编号
    private $secret = '6fbdf200f7b11d1bc124b5f9dc17bff3';//secret
    private $aeskey = 'f7b11d1bc124b5f9';//aeskey

    private $tokens;

    const APIHOST = 'https://api.yeeshui.com';

    function __construct(){
        $this->userName = Db::name('config')->where(['key'=>'user_name'])->value('val');
        $this->password = Db::name('config')->where(['key'=>'password'])->value('val');
        $this->login();
    }
    private function sign(){
        $signData['user_name']  = $this->userName;
        $signData['password']   = $this->password;
        $signData['timestamp']  = time();
        ksort($signData);
        $signString = http_build_query($signData) . '&secret='.$this->secret;
        return md5($signString);
    }
    /**
     * 登录获取token
     * @return false|void
     */
    private function login(){
        $parameters = [
            'user_name'=>$this->userName,
            'password'=>$this->password,
            'timestamp'=>time(),
            'sign'=>$this->sign()
        ];
        $result = $this->doPost(self::APIHOST.'/sdk/v1/login',$parameters);
        if($result['code'] == 200){
            $this->tokens = $result['token'];
        }else{
            return false;
        }
    }
    private function doPost($url,$parameters){
        $res = Curl::main()->url($url)->post(json_encode($parameters));
        return json_decode($res,true);
    }

    /**
     * 新增人员
     * @param $parameters
     * @return mixed
     */
    public function add_member($parameters){
        $paramet = [
            'token'=>$this->tokens,
            'data'=>$parameters
        ];
        return $this->doPost(self::APIHOST.'/Enterprise/addEmployee',$paramet);

    }
    
    /**
     * 删除人员
     * @param $facilitator_id
     * @return mixed
     */
    public function del_member($facilitator_id){
        $paramet = [
            'token'=>$this->tokens,
            'data'=>[
                'enterprise_professional_facilitator_id'=>$facilitator_id
            ],

        ];
        return $this->doPost(self::APIHOST.'/Enterprise/contractDo',$paramet);

    }

    
    /**
     * 电子签地址
     * @return void
     */
    public function Signature($parameters){
        $paramet = [
            'token'=>$this->tokens,
            'data'=>$parameters
        ];
        return $this->doPost(self::APIHOST.'/Enterprise/applySignUrl',$paramet);

    }

    /**
     * 查询签约结果
     * @param $parameters
     * @return mixed
     */
    public function checkAuth($parameters){
        $paramet = [
            'token'=>$this->tokens,
            'data'=>$parameters
        ];
        return $this->doPost(self::APIHOST.'/Enterprise/querySignResult',$paramet);
    }

    /**
     * 新增收款账户
     * @param $parameters
     * @return mixed
     */
    public function add_bank($parameters){
        $paramet = [
            'token'=>$this->tokens,
            'data'=>$parameters
        ];
        return $this->doPost(self::APIHOST.'/Enterprise/addBank',$paramet);
    }

    /**
     * 云众包3.0付款
     * @param $parameters
     * @return mixed
     */
    public function payment($parameters){
        $paramet = [
            'token'=>$this->tokens,
            'data'=>$parameters
        ];
        return $this->doPost(self::APIHOST.'/Enterprise/fastIssuing',$paramet);
    }

    /**
     * 批次审核
     * @param $enterprise_id 订单ID
     * @return mixed
     */
    public function changeOrder($enterprise_id){
        $paramet = [
            'token'=>$this->tokens,
            'data'=>[
                'enterprise_order_id'=>$enterprise_id,
                'status'=>1,
                'remarks'=>'通过',
                'apply_img'=>'',
                'verfiy_code'=>'',
                'seal_img'=>'',
            ]
        ];
        return $this->doPost(self::APIHOST.'/Enterprise/changeOrderStatus',$paramet);
    }

    /**
     * 回调验签
     * @param $parameters
     * @return bool
     */
    public function rsaCheck($parameters){
        $sign = $parameters['sign'];
        unset($parameters['sign']);
        ksort($parameters);
        $string = md5(http_build_query($parameters). "&secret={$this->secret}");
        if($sign == $string){
            return  true;
        }
        return false;

    }
}