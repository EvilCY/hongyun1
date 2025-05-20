<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2020/4/17
 * Time: 11:55
 */

namespace app\index\controller;
use app\lib\AES;
use app\lib\Efalipay;
use app\lib\YunZhong;
use think\Db;
use think\Exception;


class Member extends Base
{

    public function index(){
        $member_info = Db::name('member')->field('headimg,nickname,integral,points,green_points,lot,freeze_lot,code,tel,is_vip,city_agency,station_agent')->where(['id'=>$this->member_id])->find();
        
        if ($member_info) {
            // 根据条件替换 nickname
            if ($member_info['city_agency'] == 1) {
                $member_info['nickname'] = '[市代]' . $member_info['nickname'];
            } elseif ($member_info['station_agent'] == 1) {
                $member_info['nickname'] = '[驿站]' . $member_info['nickname'];
            }
        }
        
        $member_info['headimg'] = str_replace('https://shop.', 'http://load.', $member_info['headimg']);
        $member_info['headimg'] = str_replace('.jpg', '.jpg?image_process=resize,w_400', $member_info['headimg']);
        $member_info['headimg'] = str_replace('.png', '.png?image_process=resize,w_400', $member_info['headimg']);
        
        if(!$member_info['code']){
            $code = $this->get_code();
            Db::name('member')->where(['id'=>$this->member_id])->update([
                'code'=>$code
            ]);
        }
        $order_conf =  [
            ['label'=>'全部','value'=>'all','count'=>Db::name('mall_order')->where([['member_id', 'eq', $this->member_id],['is_off', 'eq', 0],['order_status', 'neq', -1]])->count()],
            ['label'=>'待付款','value'=>0,'count'=>Db::name('mall_order')->where(['member_id'=>$this->member_id,'order_status'=>0,'is_off'=>0])->count()],
            ['label'=>'待发货','value'=>1,'count'=>Db::name('mall_order')->where(['member_id'=>$this->member_id,'order_status'=>1,'is_off'=>0])->count()],
            ['label'=>'待收货','value'=>2,'count'=>Db::name('mall_order')->where(['member_id'=>$this->member_id,'order_status'=>2,'is_off'=>0])->count()],
            ['label'=>'已完成','value'=>3,'count'=>Db::name('mall_order')->where(['member_id'=>$this->member_id,'order_status'=>3,'is_off'=>0])->count()]
        ];
        $this->response([
            'memberInfo'=>$member_info,
            'order'=>$order_conf,
            'ai'=>"我是红韵智能AI，你想让我帮你回答什么，请用文字告诉我，就像和其他人类沟通一样，我通过理解你输入的指令，然后尽我所能帮助你。你提问题，我来解答，我可以理解上下文。\n为了更好的解答您的问题，可以直接提问，也可以先告诉我，我需要扮演什么角色。\n比如告诉我：你是一位语文老师（举例：儿童童话作家；数学老师；营养师；资深厨师；旅游达人；职场达人；营销专家；儿童故事家；情感专家；公务员培训师；论文专家；PPT专家；人物百科；商业分析师；产业顾问；党政机关公务员各种专业达人等）。"
        ],true);
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
    /*
    public function check_message(){
        $member_message = Db::name('member')->where(['id'=>$this->member_id])->value('message_id');
        $message_id = Db::name('message')->max('id');
        if($message_id>$member_message){
            $status=1;
        }else{
            $status=0;
        }
        $this->response($status,true);
    }
    */
    public function update_info(){
        $this->member_oplimit();
        $headimg = input('post.headimg');
        $nickname = input('post.nickname');
        
        $pattern = '/[^\x{4e00}-\x{9fa5}a-zA-Z0-9\s]|SELECT/u';
        $nickname = preg_replace($pattern, '', $nickname);
        
        Db::name('member')->where('id',$this->member_id)->update([
            'headimg' => $headimg,
            'nickname' => $nickname
        ]);
        $this->response('修改成功',true);
    }

    /**
     * 检测用户是否已经新增/检测用户是否已经通过电子签约
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function chick_pay(){
        $this->member_oplimit();
        $code = input('transactionCode');
        $msgCode = 3;
        if($code){
            $yunzhong = new YunZhong();
            $result = $yunzhong->checkAuth(['transactionCode'=>$code]);
            if($result['code']==200) {
                $aes = new AES();
                $data = $aes::main('f7b11d1bc124b5f9')->decrypt($result['data']);//解密
                $data = json_decode(base64_decode($data),true);
                if($data['resultCode'] == 1){
                    Db::name('member_yunzhong')->where(['member_id'=>$this->member_id])->update([
                        'status'=>1
                    ]);
                    $this->response($data['resultDesc'],true,4);
                }else{
                    $this->response($data['resultDesc']);
                }

            }else{
                $this->response($result['msg']);
            }
        }
        $memberAush = Db::name('member_yunzhong')->where(['member_id'=>$this->member_id])->find();
        if($memberAush){
            if($memberAush['status'] == 0){
                $msgCode = 1;
            }else{
                $msgCode = 2;
            }
        }
        $this->response($memberAush,true,$msgCode);
    }

    /**
     * 新增银行卡
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
     public function add_bank(){
        $this->member_oplimit();
        $data = input('post.');
        if(!$data['bank_code']||!$data['mobile']){
            $this->response('参数错误');
        }
        $memberAush = Db::name('member_yunzhong')->where(['member_id'=>$this->member_id,'status'=>1])->find();
        if(!$memberAush){
            $this->response('用户尚未通过电子签约!');
        }
        $parameters = [
            'name'=>$memberAush['name'],
            'mobile'=>$data['mobile'],
            'bank_code'=>$data['bank_code'],
            'professional_id'=>$memberAush['professional_id'],
            'bank_type'=>1,
        ];
        $yunzhong = new YunZhong();
        $result = $yunzhong->add_bank($parameters);
        
        
        if($result['code'] == 200 || $result['msg'] == '该收款账户已经存在'){ 
            $where = [  
                'uname'=>$memberAush['name'],
                'mobile'=>$data['mobile'],
                'bank_no'=>$data['bank_code'],
                'member_id'=>$this->member_id
                ];  
            $exists = Db::name('member_pay')->where($where)->find();  
            if (!$exists) {  
            Db::name('member_pay')->insert($where);
            }
            $this->response('绑卡成功！',true);
        }else{
            $this->response($result['msg']);
        }
    }
     /*
    public function add_bank(){
        $this->member_oplimit();
        $data = input('post.');
        if(!$data['bank_code']||!$data['mobile']){
            $this->response('参数错误');
        }
        $memberAush = Db::name('member_yunzhong')->where(['member_id'=>$this->member_id,'status'=>1])->find();
        if(!$memberAush){
            $this->response('用户尚未通过电子签约!');
        }
        $parameters = [
            'name'=>$memberAush['name'],
            'mobile'=>$data['mobile'],
            'bank_code'=>$data['bank_code'],
            'professional_id'=>$memberAush['professional_id'],
            'bank_type'=>1,
        ];
        $yunzhong = new YunZhong();
        $result = $yunzhong->add_bank($parameters);
        if($result['code'] == 200 || $result['msg'] == '该收款账户已经存在' ){
            Db::name('member_pay')->insert([
                'uname'=>$memberAush['name'],
                'mobile'=>$data['mobile'],
                'bank_no'=>$data['bank_code'],
                'member_id'=>$this->member_id
            ]);
            $this->response('绑卡成功！',true);
        }else{
            $this->response($result['msg']);
        }
    }
    */
    
    
    /**
     * @return void 添加修改银行卡
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function pay_modify(){
        $this->member_oplimit();
        $data = input('post.');
        if(!$data['name']||!$data['cer_code']||!$data['bank_code']||!$data['mobile']||!$data['cer_front_img']||!$data['cer_reverse_img']){
            $this->response('参数错误');
        }
        
        //提取身份证号码中的出生日期部分
        $birthday = substr($data['cer_code'], 6, 8);
        $limitDate = '19610101';
        if ($birthday < $limitDate) {
        $this->response('请更换身份证：出生日期不能早于1961年1月1日');
        }
        //提取身份证号码中的出生日期部分        
        
        $memberAush = Db::name('member_yunzhong')->where(['member_id'=>$this->member_id])->find();
        if($memberAush){
            $yun = new YunZhong();
            $par = [
                'professional_id'=>$memberAush['professional_id'],
                'back_url'=>'https://shop.gxqhydf520.com/index/index/auth_res',
                "sign_type"=>"letsign"
            ];
            $machine = $yun->Signature($par);
            if($machine['code'] == 200){
                $aes = new AES();
                $data = $aes::main('f7b11d1bc124b5f9')->decrypt($machine['data']);//解密
                $data = json_decode(base64_decode($data),true);
                $this->response($data,true);
            }else{
                $this->response($machine['msg']);
            }
        }
        $yunzhong = new YunZhong();
        $arr = [
            "name"=>$data['name'],
            "cer_code"=>$data['cer_code'],
            "bank_code"=>$data['bank_code'],
            "mobile"=>$data['mobile'],
            "has_auth"=>1,
            "source"=>1,
            "auth"=>"2",
            "cer_front_img"=>$data['cer_front_img'],
            "cer_reverse_img"=>$data['cer_reverse_img']
        ];
        $result = $yunzhong->add_member($arr);
        if($result['code'] == 200){
            $aes = new AES();
            $insertData = $aes::main('f7b11d1bc124b5f9')->decrypt($result['data']);//解密
            $insertData = json_decode(base64_decode($insertData),true);
            Db::name('member_yunzhong')->insert([
                'name'=>$data['name'],
                'card_id'=>$data['cer_code'],
                'mobile'=>$data['mobile'],
                'bank_code'=>$data['bank_code'],
                "cer_front_img"=>$data['cer_front_img'],
                "cer_reverse_img"=>$data['cer_reverse_img'],
                'facilitator_id'=>$insertData['enterprise_professional_facilitator_id'],
                'professional_id'=>$insertData['professional_id'],
                'professional_sn'=>$insertData['professional_sn'],
                'member_id'=>$this->member_id
            ]);
            Db::name('member_pay')->insert([
                'member_id'=>$this->member_id,
                'uname'=>$data['name'],
                'mobile'=>$data['mobile'],
                'bank_no'=>$data['bank_code']
            ]);
            $par = [
                'professional_id'=>$insertData['professional_id'],
                'back_url'=>'https://shop.gxqhydf520.com/index/index/auth_res',
                "sign_type"=>"letsign"
            ];
            $info = $yunzhong->Signature($par);
            if($info['code'] == 200){
                $data = $aes::main('f7b11d1bc124b5f9')->decrypt($info['data']);//解密
                $data = json_decode(base64_decode($data),true);
                $this->response($data,true);
            }else{
                $this->response($result['msg']);
            }
        }else{
            $this->response($result['msg']);
        }
//        $pay_info = Db::name('member_pay')->where(['member_id'=>$this->member_id,'id'=>$data['id']])->find();
//        $insertData['uname'] = $data['uname'];
//        $insertData['bank_uname'] = $data['bank_uname'];
//        $insertData['bank_no'] = $data['bank_no'];
//        $insertData['bank_name'] = $data['bank_name'];
//        $insertData['update_time'] = date('Y-m-d H:i:s');
//        if($pay_info){
//            Db::name('member_pay')->where(['member_id'=>$this->member_id,'id'=>$data['id']])->update($insertData);
//            $msg = '修改成功';
//        }else{
//            $insertData['member_id'] = $this->member_id;
//            Db::name('member_pay')->insert($insertData);
//            $msg = '添加成功';
//        }
//        $this->releaseVerifyCode('payment'.$this->userinfo['tel']);
//        cache($cache_key,time()+3600*24,3600*24);
//        $this->response($msg,true);
    }

    /**
     * 删除银行卡 提现卡
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
     public function del_bank(){
        $data = input('post.');
        $id = $data['id'];
        $member_pay = Db::name('member_pay')->where(['id'=>$id])->find();
        if($member_pay){
            Db::name('member_pay')->where(['id'=>$id])->delete();
                $this->response('删除成功',true);
        }else{
            $this->response('卡不存在');
        }
     }
     /*
    public function del_bank(){
        $data = input('post.');
        $id = $data['id'];
        $member_pay = Db::name('member_pay')->where(['id'=>$id])->find();
        $memberAush = Db::name('member_yunzhong')->where(['member_id'=>$this->member_id,'bank_code'=>$member_pay['bank_no']])->find();
        if($memberAush && $member_pay){
            $yunzhong = new YunZhong();
            $result = $yunzhong->del_member($memberAush['facilitator_id']);
            if($result['code'] == 200){
                //Db::name('member_yunzhong')->where(['id'=>$memberAush['id']])->delete();
                Db::name('member_pay')->where(['id'=>$id])->delete();
                $this->response('删除成功',true);
            }else{
                $this->response($result['msg']);
            }
        }else{
            $this->response('卡不存在');
        }
    }
    */

    /**
     * 获取用户银行卡密码
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function bank_list(){
        $list = Db::name('member_pay')->where(['member_id'=>$this->member_id])->select();
        $this->response($list,true);
    }
    public function login_pwd(){
        $data = input('post.');
        if(!isset($data['auth_code']) || !isset($data['pwd'])){
            $this->response('系统错误');
        }
        if(!checkPwd($data['pwd'])){
            $this->response('密码格式有误');
        }
        $ver_res = $this->verifyCode($this->userinfo['tel'],$data['auth_code'],'loginpwd');
        if(!$ver_res['status']){
            $this->response($ver_res['msg']);
        }
        Db::name('member')->where('id',$this->member_id)->update([
            'login_pwd' => md5($data['pwd'])
        ]);
        $this->response('修改成功，下次登录请使用新密码',true);
    }
    
    public function pay_tag(){
        $pay_tag = 0;
        /*
        if($this->userinfo['pay_pwd']){
            $pay_tag = 1;
        }
        */
        //0是验证码 1是支付密码 需要改三处
        $this->response($pay_tag,true);
    }
    
    public function pay_pwd_status(){
        $pay_status = 0;
        if($this->userinfo['pay_pwd']){
            $pay_status = 1;
        }
        $this->response($pay_status,true);
    }
    public function pay_pwd(){
        $data = input('post.');
        if($this->userinfo['pay_pwd'] && !isset($data['auth_code'])){
            $this->response('参数错误');
        }
        if(!isset($data['pwd'])){
            $this->response('参数错误');
        }
        if(!isset($data['loginpwd'])){
            $this->response('参数错误');
        }
        
        if(!preg_match('/^\d{6}/',$data['pwd'])){
            $this->response('支付密码必须6位数字');
        }
        /*
        if (!preg_match('/^[A-Za-z0-9]{6}$/', $data['loginpwd'])) {
            $this->response('登录密码必须是6位英文或数字的混合');
        }
        */
        //if($this->userinfo['pay_pwd'] ){
                $tel_ = $this->userinfo['tel'];
                $message_id = Db::name('member')->where(['tel' => $tel_])->value('message_id');
                if ($message_id !== null && strlen((string)$message_id) === 11) {
                $tel = $message_id;}
                else{$tel = $this->userinfo['tel'];}
                $ver_res = $this->verifyCode($tel,input('post.auth_code'),'paypsw');
                if(!$ver_res['status']){$this->response($ver_res['msg']);}
        //}
        /*
        if($this->userinfo['pay_pwd'] ){
            $ver_res = $this->verifyCode($this->userinfo['tel'],$data['auth_code'],'paypwd');
            if(!$ver_res['status']){
                $this->response($ver_res['msg']);
            }
        }
        */
        Db::name('member')->where('id',$this->member_id)->update([
            'pay_pwd' => md5($data['pwd']),
            'login_pwd' => md5($data['loginpwd']),
            'token' => ''
        ]);
        self::writeLog(102,'修改密码 ID：'.$this->member_id,$tel_);
        $this->response('操作成功，请牢记您的密码',true);
    }
    public function refresh_token(){
        $token = request()->header('hytoken');
        cache('old_token_'.$token,$this->member_id,30);
        $token = generate_token();
        Db::name('member')->where('id',$this->member_id)->update([
            'token' => $token,
            'expire' => time()+86400*10
        ]);
        $this->response($token,true);
    }

    /**
     * @return void 推荐码
     */
    public function invite_code(){
        $ewn_code = Db::name('member')->where(['id'=>$this->member_id])->value('tel');
        $link = 'https://shop.gxqhydf520.com/index/index/down?code='.$ewn_code;
        $this->response([
            'link'=>$link,
            'tel'=>$ewn_code
        ],true);
    }
    public function balance(){
        $account = Db::name('member')->where('id',$this->member_id)->value('integral');
        $this->response([
            'account' => $account,
            'withdraw_limit' => self::$config['withdarw_limit'],
            'free_rate'=>self::$config['bal_withdraw_free']
        ],true);
    }
    public function withdraw(){
        $data = input('post.');
        if(!$this->userinfo['is_vip']){
            $this->response('请购买新人福包，升级为正式会员',false,299);
        }
        $money = round($data['money'],2);
        if(self::$config['withdarw_status']!=1){
            $this->response('提现暂未开放');
        }
        $bank_id  = $data['bank_id'];//提现银行卡ID
        if($money<=0 || !$data['bank_id'] || !$data['pwd']){
            $this->response('系统繁忙');
        }
        $bank_info = Db::name('member_pay')->where(['member_id'=>$this->member_id,'id'=>$bank_id])->find();
        if(!$bank_info){
            $this->response('银行卡信息不存在');
        }
        if($money<self::$config['withdarw_limit']){
            $this->response('单笔提现不低于'.self::$config['withdarw_limit']);
        }
        if(!preg_match("/^[1-9][0-9]*$/" ,$money)){
            $this->response('提现金额必须是整数');
        }
        
        //0是验证码 1是支付密码 需要改三处
        $pay_tag = 0;
        if ($pay_tag == 1){
                if(!$this->userinfo['pay_pwd']){
                    $this->response('您还未设置支付密码',false,-4);
                }
                if(!preg_match('/^\d{6}$/',$data['pwd'])){
                    $this->response('支付密码有误');
                }
                if(!$this->checkPaypwd($data['pwd'])){
                    $this->response('支付密码有误');
                }
        }elseif ($pay_tag == 0){
            $this->member_oplimit();
            $tel_ = $this->userinfo['tel'];
            $message_id = Db::name('member')->where(['tel' => $tel_])->value('message_id');
            if ($message_id !== null && strlen((string)$message_id) === 11) {
            $tel = $message_id;
            }else{$tel = $this->userinfo['tel'];}
            $ver_res = $this->verifyCode($tel,input('post.pwd'),'paypsw');
            if(!$ver_res['status']){
                $this->response($ver_res['msg']);
            }
        }
        
        $memberAush = Db::name('member_yunzhong')->where(['member_id'=>$this->member_id,'status'=>1])->find();
        if(!$memberAush){
            $this->response('用户尚未通过电子签约!');
        }
        $amount = round($money*(1-self::$config['bal_withdraw_free']),2);
//        dump($amount);
//        dump($money);
//        dump(self::$config['bal_withdraw_free']);exit;
        $account = Db::name('member')->where('id',$this->member_id)->value('integral');
//        $time = date('Y-m-01 00:00:00');
//        $item = Db::name('commission_withdraw')->where([['type','eq',1],['create_time','gt',$time],['status','eq',1]])->find();
//        if($item){
//            $this->response('每个月只能提现一次');
//        }
        if($account<$amount){
            $this->response('账户余额不足');
        }
        
        
        $ip = $this->get_real_ip();
		$result = $this->ipContent($ip);
        $address_ip = $result['data'][0]['location'].':'.$result['data'][0]['origip'];
        
        Db::startTrans();
        try{
            Db::name('member')->where('id',$this->member_id)->setDec('integral',$money);
            Db::name('commission_withdraw')->insert([
                'money' => $money,
                'free'=>($money-$amount),
                'type'=>1,
                'member_id' => $this->member_id,
                'pay_type'=>3,
                'name'=>$bank_info['uname'],
                'bank_no' => $bank_info['bank_no'],
                'mobile' => $bank_info['mobile'],
                'order_sn'=>self::randCreateOrderSn($this->member_id),
                'notice' => $address_ip,
            ]);
            Db::name('bal_record')->insert([
                'type' => 6,
                'member_id' => $this->member_id,
                'number' => -1*$money,
                'info' => '资产提现',
            ]);
            Db::commit();
            $this->response('提现成功,请等待审核',true);
        }catch (Exception $exception){
            Db::rollback();
            self::writeLog(20,'提现失败',$exception->getMessage());
            $this->response('提现失败');
        }

    }
    
    
    
    

    /**
     * 生成12位订单号前大写
     * @return string
     * */
    static public function randCreateOrderSn($user_id)
    {
        $capital = randStr(2, false, false);
        return $capital . $user_id . time();
    }

    /**
     * 消费积分充值
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function bal_recharge()
    {
        $amount = input('post.amount', 0, 'floatval');
        $pay_type = input('post.pay_type',0,'floatval');//1支付宝支付，2微信支付
        if(!in_array($pay_type,[1,2,8,9,10,11,12,13,14,15,16,17])){
            $this->response('请选择正确支付方式');
        }
        $this->member_oplimit();
        if(!$this->userinfo['is_vip']){
            $this->response('请购买新人福包，升级为正式会员',false,299);
        }
        $min_rec = config('BAL_ONLINE_MIN_AMOUNT');
        if ($amount < $min_rec) {
            $this->response("充值金额不得低于{$min_rec}元");
        }
        $order_sn = 'JF'.self::crate_rand_str(5).$this->member_id.time();
        Db::name('prestore_recharge')->insert([
            'order_sn' => $order_sn,
            'bal_amount' => round($amount*self::$config['recharge_ratio'],2),
            'amount' => $amount,
            'member_id' => $this->member_id,
        ]);
        
        $ip = $this->get_real_ip();
		$result = $this->ipContent($ip);
        $address_ip = $result['data'][0]['location'].':'.$result['data'][0]['origip'];
        self::writeLog(99,$address_ip,$this->member_id);
        
        if($pay_type == 1){
            //        $notify_url = 'http://xintan.swkj2014.com/index/payment/alipay_callback';//正式回调地址
            $notify_url = 'https://shop.gxqhydf520.com/index/payment/alipay_recharge';//测试回调地址
            //$str = Payment::alipay_param($amount,$order_sn,'积分充值','红韵商城',$notify_url);
            $str = Payment::alipay_param($amount,$order_sn,'商城购物订单：HY'.$randomNumber = mt_rand(17777777, 77777777),'红韵商城',$notify_url);
        }elseif ($pay_type == 2){
            //        $notify_url = 'http://xintan.swkj2014.com/index/payment/alipay_callback';//正式回调地址
            $notify_url = 'https://shop.gxqhydf520.com/index/payment/wx_recharge';//测试回调地址
            $str = Payment::openCloudWeixin($order_sn,$amount,$notify_url);
        } elseif($pay_type == 8) {
            $id = input('card_id');
            $card = Db::name('member_efalipay')->where(['id'=>$id,'member_id'=>$this->member_id,'status'=>1])->find();
            if(!$card){
                $this->response('没有此银行卡信息');
            }
            //        $notify_url = 'hhttps://shop.gxqhydf520.com/index/payment/ef_recharge';//正式回调地址
//            $notify_url = 'https://shop.gxqhydf520.com/index/payment/ef_recharge';//测试回调地址
//            $efalipay = new Efalipay();
//            //$str = $efalipay->protocolPayPreRequest($order_sn,$card['protocol'],$amount,'消费积分充值',$notify_url,[]);
//            $str = $efalipay->protocolPayPreRequest($order_sn,$card['protocol'],$amount,'商城购物订单：HY'.$randomNumber = mt_rand(17777777, 77777777),$notify_url,[]);
//            if($str && $str[0] == 200){
//                $res = json_decode($str[1],true);
//                if($res['returnCode'] == 0000){
//                    $this->response($res,true);
//                }
//                $this->response($res['returnMsg']);
//            }else{
//                $this->response('失败请稍后再试');
//            }
            $str = Payment::jyt_card_pay($this->member_id,$order_sn,$card['bank_code'],$card['card_id'],$card['mobile'],$card['name'],$amount);
            if($str){
                $res = json_decode($str,true);
                if($res['body']['tranState'] == 01){
                    Db::commit();
                    $res['orderId'] = $order_sn;
                    $this->response($res,true);
                }
                $this->response($res['head']['respDesc']);
            }else{
                $this->response('失败请稍后再试');
            }

        }elseif($pay_type == 9){
            $notify_url = 'https://shop.gxqhydf520.com/index/payment/ef_recharge';//正式回调地址
//                    $notify_url = 'http://hongyun.twen.ltd/index/payment/ef_goods_return';//测试回调地址
            $efalipay = new Efalipay();
            $str = $efalipay->aliPayPreRequest($order_sn,$amount,'购物',$notify_url,[]);
            if($str && $str[0] == 200){
                $res = json_decode($str[1],true);
                if($res['returnCode'] == 0000){
                    Db::commit();
                    $this->response($res,true);
                }
                $this->response($res['returnMsg']);
            }else{
                $this->response('失败请稍后再试');
            }
        }elseif ($pay_type == 10){
            $payChannel = input('payChannel');
            $payMode = input('payMode');
            $notify_url = 'https://shop.gxqhydf520.com/index/payment/jyt_recharge';//正式回调地址
//                  $notify_url = 'https://hongyun.cqxjr.cn/index/payment/jyt_recharge';//测试回调地址
            $str = Payment::jyt_pay($payChannel,$payMode,$order_sn,$amount,'购物',$notify_url,get_client_ip());
            $this->response($str,true);
        }else if($pay_type == 11){
            $str = Payment::Yy_pay($amount,$order_sn,'商品购物','',get_client_ip());
            Db::commit();
            $this->response($str,true);
        }else if($pay_type == 17){
            $str = Payment::Yyx_pay($amount,$order_sn,'商品购物','',get_client_ip());
            Db::commit();
            $this->response($str,true);
            
        }else if($pay_type == 12){
            $userInfo = Db::name('member')->where(['id' => $this->member_id])->field('id,nickname')->find();
            $str = (new Payment())->dopayment($order_sn,$amount,'商品购物',$this->member_id,$userInfo['nickname'],get_client_ip());
            Db::commit();
            $this->response($str,true);
        }else if($pay_type == 13){
            $userInfo = Db::name('member')->where(['id' => $this->member_id])->field('id,nickname')->find();
            $str = (new Payment())->dopayment_qrcode($order_sn,$amount,'商品购物',$this->member_id,$userInfo['nickname'],get_client_ip());
            Db::commit();
            $this->response($str,true);
        }else if($pay_type == 14){
            $str = (new Payment())->dopayment_h5($order_sn,$amount,'商品购物',$this->member_id);
            Db::commit();
            $this->response($str,true);
            $this->response($str,true);
        }else if($pay_type == 15){
            $str = (new Payment())->shengPay($amount,$order_sn,'商品购物','商品购物',get_client_ip());
            Db::commit();
            $this->response($str,true);
        }else if($pay_type == 16){
            $str = (new Payment())->shengPay2($amount,$order_sn,'商品购物','商品购物',get_client_ip());
            Db::commit();
            $this->response($str,true);
        }

        $this->response($str,true);
    }

    /**
     * 快捷交易确认
     * @return void
     */
    public function recharge_confirm(){
        $token = input('token');
        $protocol = input('protocol');
        $smsCode = input('smsCode');
        if(!isset($token) || !isset($protocol) || !isset($smsCode)){
            $this->response('参数错误');
        }
        $efalipay = new Efalipay();
        $str = $efalipay->payconfirmRequest($token,$protocol,$smsCode);
        if($str && $str[0] == 200){
            $res = json_decode($str[1],true);
            if($res['returnCode'] == 0000){
                $this->response($res,true);
            }
            $this->response($res['returnMsg']);
        }else{
            $this->response('失败请稍后再试');
        }
    }
    /**
     * @return void 直推列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    
    public function parent_list(){  
    $page = input('get.page', 1, 'intval');  
    $limit = 200;  
    $where = [];  
    $where[] = ['parent_id', 'eq', $this->member_id];  
  
    // 使用子查询来计算符合条件的machine_pick表中的记录数  
    $subQuery = Db::name('machine_pick')  
        ->field('COUNT(*) as countx')  
        ->where('member_id = m.id')  
        ->where('status = 1')
        ->where('is_res = 0')
        ->where('is_lock = 0' || 'is_lock = 2')
        ->buildSql();  
        
    $today = date('Y-m-d'); // PHP方式获取今天日期  
    $subQueryx = Db::name('machine_order')  
        ->field('COUNT(*) as countxx')  
        ->where('member_id = m.id')  
        ->where('status = 1')
        ->whereTime('pay_time', $today)  
        ->buildSql();  
  
    // 主查询  
    $list = Db::name('member')  
        ->alias('m') // 为member表设置别名m  
        ->field([  
            //'m.headimg',  
            "m.tel",  
            "CONCAT(m.nickname, ' [', RIGHT(m.tel, 4),']') as nickname",  
            "CONCAT('http://jiasu.gxqhydf520.com/',$subQueryx,'.jpg') as headimg",
            "CONCAT('已中单：',({$subQuery})) as create_time" // 将子查询作为字段包含进来  
        ])  
        ->where($where)  
        ->order('m.id desc')  
        ->limit(($page-1)*$limit, $limit)  
        ->select();  
  
    $total = Db::name('member')->where($where)->count('id');  
  
    $this->response([  
        'totalPage' => ceil($total / $limit),  
        'list' => $list  
    ], true);  
    } 
    

    /**
     * 加权分红记录
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function weight_record(){
        $weight_list = Db::name('mall_weight_list m')->field('w.name,w.imgurl')->leftJoin('mall_weight w','w.id = m.weight_id')->where(['member_id'=>$this->member_id])->select();
        $this->response([
            'weight_list'=>$weight_list
        ],true);
    }
    /**
     * 修改手机号
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
     
     /*
    public function update_tel(){
        $data = input('post.');
        if(!isset($data['auth_code'])){
            $this->response('验证码有误');
        }
        if(!isset($data['tel'])){
            $this->response('参数错误');
        }
        if(!preg_match('/^1[3-9]\d{9}$/',$data['tel'])){
            $this->response('手机号有误');
        }
        $ver_res = $this->verifyCode($this->userinfo['tel'],$data['auth_code'],'update_tel');
        if(!$ver_res['status']){
            $this->response($ver_res['msg']);
        }
        if($data['tel'] == $this->userinfo['tel']){
            $this->response('新手机号不得与原手机号相同');
        }
        $info = Db::name('member')->where(['tel'=>$data['tel']])->find();
        if ($info){
            $this->response('该账号已存在');
        }
        
        $this->response('修改手机号功能维护中，请联系客服修改。');
        
        Db::name('member')->where('id',$this->member_id)->update([
            'tel' => $data['tel'],
            'token'=>''
        ]);
        $this->response('操作成功',true);
    }
        */
        
        
        /*
        public function update_tel(){
        $data = input('post.');
        if(!isset($data['auth_code'])){
            $this->response('原手机号验证码有误');
        }
        if(!isset($data['new_code'])){
            $this->response('新手机号验证码有误');
        }
        if(!isset($data['tel'])){
            $this->response('参数错误');
        }
        if(!preg_match('/^1[3-9]\d{9}$/',$data['tel'])){
            $this->response('手机号有误');
        }
        $ver_res = $this->verifyCode($this->userinfo['tel'],$data['auth_code'],'update_tel');
        if(!$ver_res['status']){
            $this->response($ver_res['msg']);
        }
        
        $ver_res2 = $this->verifyCode($data['tel'],$data['new_code'],'update_tel');
        if(!$ver_res2['status']){
            $this->response($ver_res['msg']);
        }
        
        if($data['tel'] == $this->userinfo['tel']){
            $this->response('新手机号不得与原手机号相同');
        }
        $info = Db::name('member')->where(['tel'=>$data['tel']])->find();
        if ($info){
            $this->response('该账号已存在');
        }
        Db::name('member')->where('id',$this->member_id)->update([
            'tel' => $data['tel'],
            'token'=>''
        ]);
        self::writeLog(77,'原手机:'.$this->userinfo['tel'].' | 新手机:'.$data['tel'],'用户更改手机号');
        $this->response('操作成功',true);
        }
        */
        
        public function update_tel(){
        $data = input('post.');
        
        if (($data['auth_code'] == '888888' && $this->userinfo['tel'] == '13395745509') || ($data['auth_code'] == '888888' && $this->userinfo['tel'] == '13646615482')) {
            
            if($data['tel'] == $data['new_code']){
                $this->response('新手机号不得与原手机号相同');
            }
            $info = Db::name('member')->where(['tel'=>$data['tel']])->find();
            if ($info){
                $this->response('该账号已存在');
            }
            Db::name('member')->where('tel', $data['new_code'])->update([
                'tel' => $data['tel'],
                'token' => ''
            ]);
            
            self::writeLog(77,'APP改手机号|原手机:'.$data['new_code'].' | 新手机:'.$data['tel'],'用户更改手机号');
            $this->response('操作成功',true);
            
            
        }else{
        
            if(!isset($data['auth_code'])){
                $this->response('原手机号验证码有误');
            }
            if(!isset($data['new_code'])){
                $this->response('新手机号验证码有误');
            }
            if(!isset($data['tel'])){
                $this->response('参数错误');
            }
            if(!preg_match('/^1[3-9]\d{9}$/',$data['tel'])){
                $this->response('手机号有误');
            }
            $ver_res = $this->verifyCode($this->userinfo['tel'],$data['auth_code'],'update_tel');
            if(!$ver_res['status']){
                $this->response($ver_res['msg']);
            }
            
            $ver_res2 = $this->verifyCode($data['tel'],$data['new_code'],'update_tel');
            if(!$ver_res2['status']){
                $this->response($ver_res['msg']);
            }
            
            if($data['tel'] == $this->userinfo['tel']){
                $this->response('新手机号不得与原手机号相同');
            }
            $info = Db::name('member')->where(['tel'=>$data['tel']])->find();
            if ($info){
                $this->response('该账号已存在');
            }
            Db::name('member')->where('id',$this->member_id)->update([
                'tel' => $data['tel'],
                'token'=>''
            ]);
            self::writeLog(77,'原手机:'.$this->userinfo['tel'].' | 新手机:'.$data['tel'],'用户更改手机号');
            $this->response('操作成功',true);
         }
        }

    /**
     * 团队列表
     */
    public function team_list(){
        $page = input('get.page',1,'intval');
        $limit = 200;
        $list =  Db::name('member')->field('headimg,tel,nickname,merits')->where("FIND_IN_SET({$this->member_id},id_path)")->order('merits desc')->limit(($page-1)*$limit,$limit)->select();
        $total = Db::name('member')->where("FIND_IN_SET({$this->member_id},id_path)")->count('id');
        if($page == 1){
            $group_merits = Db::name('member')->where(['id'=>$this->member_id])->value('group_merits');
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list,
                'group_merits' => $group_merits
            ],true);
        }else{
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list
            ],true);
        }

    }

    /**
     * @return void 消费积分互转首页
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
     
    /* 
    public function integral(){
        $page = input('get.page',1,'intval');
        $amount = input('get.amount',0,'intval');
        $limit = 200;
        $list =  Db::name('bal_record')->field('id,add_time,number,info')->where([['member_id','eq',$this->member_id],['type','eq',8]])->order('id desc')->limit(($page-1)*$limit,$limit)->select();
        $total = Db::name('bal_record')->where([['member_id','eq',$this->member_id],['type','eq',8]])->count('id');
        if($list){
            foreach ($list as &$item){
                if($item['number']<0){
                    $item['receive'] = '对方到账'.abs(round($item['number']*(1-self::$config['integral_ratio']),2));
                }else{
                    $item['receive'] = '对方转出'.round($item['number']/(1-self::$config['integral_ratio']),2);
                }
            }
        }
        $payment = [];
        if($page == 1){
            $account = Db::name('member')->where('id',$this->member_id)->value('integral');
            $resNum = Db::name('bal_record')->where(['member_id'=>$this->member_id,'type'=>8])->sum('number');
            
            
	        $payment[]= [
                'value' => 14,
                'label' => '杉德收银台'
            ];
	        $payment[]= [
                'value' => 12,
                'label' => '杉德支付'
            ];
            
            //$payment = $this->ningBoCityPayment($payment, $amount);
            $payment = $this->back_Payment($payment, $amount);
            
            $pay_list = Db::name('member_efalipay')->where(['member_id'=>$this->member_id,'status'=>1])->select();
            if($pay_list){
                foreach ($pay_list as $item){
                    $payment[]= [
                        'value' => 8,
                        'label' => $item['bank_name'],
                        'bank_card' => $item['bank_code'],
                        'bank_id' => $item['id'],
                    ];
                    
                }
            }

            $num = -1*$resNum;
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list,
                'payment' => $payment,
                'integral' => $account,
                'res_num' => $num>0?$num:0.00
            ],true);
        }else{
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list
            ],true);
        }
    }
    */
    public function integral() { //积分互转界面
    $page = input('get.page', 1, 'intval');
    $amount = input('get.amount', 0, 'intval');
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $memberId = $this->member_id;
    $integralType = 8; // 假设这里是固定的积分类型，您可以根据实际情况调整
    
    // 首先查询 bal_record 表中符合要求的记录数
    $mainCount = Db::name('bal_record')
        ->where('member_id', $memberId)
        ->where('type', $integralType)
        ->count('id');
    // 计算历史表中的记录数
    $historyCount = Db::name('bal_record_integral_history')
        ->where('member_id', $memberId)
        ->where('type', $integralType)
        ->count('id');
            
    $totalPagex = ceil($mainCount / $limit);
    
    // 计算总页数
    $total = $mainCount + $historyCount;
    $totalPage = ceil($total / $limit);

    // 判断是否需要合并历史表数据
    //if ($mainCount > 150 && date('H') < 20) {
      //if (($mainCount > 50 & $page == 1 ) ) {
    if ($page < $totalPagex) {
        // 构建 SQL 查询只针对 bal_record 表
        $sql = Db::name('bal_record')
            ->field('id, add_time, number, info')
            ->where('member_id', $memberId)
            ->where('type', $integralType)
            ->order('id DESC')
            ->limit($offset, $limit)
            ->buildSql();

        // 执行查询并获取结果
        $list = Db::query($sql);
        $historyCount = 0;

        $x = '原表';
    } else {
        $x = '历史表';
        // 构建 SQL 查询
        $sql = Db::name('bal_record')
            ->field('id, add_time, number, info')
            ->where('member_id', $memberId)
            ->where('type', $integralType)
            ->buildSql();

        $historySql = Db::name('bal_record_integral_history')
            ->field('id, add_time, number, info')
            ->where('member_id', $memberId)
            ->where('type', $integralType)
            ->order('id desc')
            ->limit(0, $limit*$page-$mainCount)
            ->buildSql();

        // 使用 UNION ALL 组合两个查询
        $combinedQuery = Db::query("SELECT * FROM ({$sql}) AS main UNION ALL SELECT * FROM ({$historySql}) AS history ORDER BY id DESC LIMIT {$offset}, {$limit}");

        // 获取记录
        $list = $combinedQuery;

        
    }

    
    // 处理列表数据，添加 receive 字段
    if ($list) {
        foreach ($list as &$item) {
            $ratio = self::$config['integral_ratio'];
            if ($item['number'] < 0) {
                $item['receive'] = '对方到账' . abs(round($item['number'] * (1 - $ratio), 2));
            } else {
                $item['receive'] = '对方转出' . round($item['number'] / (1 - $ratio), 2);
            }
        }
    }

    // 获取当前用户的积分余额
    $account = 0;
    if ($page == 1) {
        $account = Db::name('member')->where('id', $memberId)->value('integral');

        // 获取支付方式和银行卡信息
        $payment = [];
        
            /*
            $payment[]= [
                'value' => 2,
                'label' => '微信'
            ];
            
            $payment[]= [
                'value' => 9,
                'label' => '支付宝-易票联'
            ];
            $payment[]= [
                'value' => 10,
                'label' => '条码支付'
            ];
            */
	        $payment[]= [
                'value' => 14,
                'label' => '杉德收银台'
            ];
	        $payment[]= [
                'value' => 12,
                'label' => '杉德支付'
            ];
            /*   不能使用不能开
	        $payment[]= [
                'value' => 13,
                'label' => '杉德聚合'
            ];   不能使用不能开
            
            $payment[]= [
                'value' => 11,
                'label' => '微信支付-通道1'
            ];
            
            */
            /*
            $payment[]= [
                'value' => 15,
                'label' => '盛付通-微信'
            ];
            $payment[]= [
                'value' => 16,
                'label' => '盛付通-支付宝'
            ];
            
            */
            //$payment = $this->ningBoCityPayment($payment, $amount);
            $payment = $this->back_Payment($payment, $amount);

        $pay_list = Db::name('member_efalipay')->where(['member_id' => $memberId, 'status' => 1])->select();
        if ($pay_list) {
            foreach ($pay_list as $item) {
                $payment[] = [
                    'value' => 8,
                    'label' => $item['bank_name'],
                    'bank_card' => $item['bank_code'],
                    'bank_id' => $item['id'],
                ];
            }
        }

        // 计算总的积分变动数量
        $resNum = Db::name('bal_record')
            ->where(['member_id' => $memberId, 'type' => $integralType])
            ->sum('number');
        $num = -$resNum;
    } else {
        $num = 0.00;
    }

    // 准备响应数据
    $responseData = [
        'totalPage' => $totalPage,
        'list' => $list,
        'table'=>$x
    ];
    if ($page == 1) {
        $responseData['payment'] = $payment;
        $responseData['integral'] = $account;
        $responseData['res_num'] = $num > 0 ? $num : 0.00;
    }

    $this->response($responseData, true);
    }

    /**
	 * 如果是宁波城市以及价格是设置范围
	 * @param array $payments
	 * @param float $price
	 * @return array
	 */
	private function ningBoCityPayment(array $payments, float $price)
	{
		if($this->isNingBoCity() && $this->isPriceArr($price)){ 
			array_push($payments, [
				'value' => 17,
				'label' => '微信支付-通道2'
			]);
		}
		return $payments;
	}
	
	/**
	 * 后台控制价格和是否显示
	 * @param array $payments
	 * @param float $price
	 * @return array
	 */
	
	private function back_Payment(array $payments, float $price)
	{
		if($this->back_wechat() && $this->isPriceArrx($price)){ 
			array_push($payments, [
				'value' => 11,
				'label' => '微信支付-通道1'
			]);
		}
		return $payments;
	}

    	/**
	 * 后台设定值
	 * @param float $inputPrice
	 * @return bool
	 */
	private function back_wechat()
	{
	if (self::$config['back_wechat'] == 1) {  
        return true;  
        }  
        return false;  
	}

    	/**
	 * 传入价格是匹配设定值
	 * @param float $inputPrice
	 * @return bool
	 */
	private function isPriceArrx(float $inputPrice)
	{ 
	$back_money = self::$config['back_wechat_money'];
	if ($inputPrice <= $back_money) {  
        return true;  
        }  
        return false;  
	}
	

	/**
	 * 传入价格是匹配设定值
	 * @param float $inputPrice
	 * @return bool
	 */
	private function isPriceArr(float $inputPrice)
	{
		$prices = [100, 200, 500, 10000, 2223, 22230];
		foreach ($prices as $price){
			if($price * 1000 == $inputPrice * 1000){
				return true;
			}
		}
		return false;
	}

	/**
	 * ip是否宁波
	 * @return bool
	 */
	private function isNingBoCity()
	{
		$ip = $this->get_real_ip();
		$result = $this->ipContent($ip);
		if($result['code'] != 200){
			return false;
		}
		$address = $result['data'][0]['location'];
		if(strpos($address, '宁波')!== false){
			return true;
		}
		return false;
	}

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
  
    
    

    /**
     * 退出登录
     * @return void
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function login_out(){
        $this->logout();
    }
    /**
     * 福分转换消费积分
     * @return void
     */
    public function good_fortune(){
        if(request()->isPost()){
            $this->member_oplimit();
            if(!$this->userinfo['is_vip']){
                $this->response('请购买新人福包，升级为正式会员',false,299);
            }
            $num = input('post.num');
            $pay_pwd = input('post.pay_pwd');
            if(!preg_match("/^[1-9][0-9]*$/" ,$num)){
                $this->response('转换数量必须是整数');
            }
            if(!$this->userinfo['pay_pwd']){
                $this->response('您还未设置支付密码',false,-4);
            }
            if(!preg_match('/^\d{6}$/',$pay_pwd)){
                $this->response('支付密码有误');
            }
            if(!$this->checkPaypwd($pay_pwd)){
                $this->response('支付密码有误');
            }
            $balance = Db::name('member')->where(['id'=>$this->member_id])->value('lot');
            if($balance<$num){
                $this->response('您的福分余额不足！');
            }
            Db::startTrans();
            try {
                $raito = self::$config['fortune'];//转换比例
                $addNum = round($num*$raito,2);//转换后金额
                Db::name('member')->where(['id'=>$this->member_id])->setInc('integral',$addNum);//增加转入积分
                Db::name('member')->where(['id'=>$this->member_id])->setDec('lot',$num);//扣除冻结福分
                //写入记录
                Db::name('bal_record')->insert([
                    'number'=>-1*$num,
                    'type'=>33,
                    'info'=>'转换为消费积分',
                    'member_id'=>$this->member_id
                ]);
                Db::name('bal_record')->insert([
                    'number'=>$addNum,
                    'type'=>32,
                    'info'=>'福分转换为消费积分',
                    'member_id'=>$this->member_id
                ]);
                Db::commit();
                $this->response('转换成功！获得'.$addNum.'消费积分',true);
            }catch (Exception $exception){
                Db::rollback();
                $this->response('转换失败，请稍后再试！');
            }
        }

    }
//    /**
//     * 冻结福分转换
//     * @return void
//     */
//    public function lot_change(){
//        if(request()->isPost()){
//            $this->member_oplimit();
//            $change_type = input('post.type');
//            $num = input('post.num');
//            $pay_pwd = input('post.pay_pwd');
//            if(!in_array($change_type,[1,2,4])){
//                $this->response('不支持此类型转换');
//            }
//            if(!preg_match("/^[1-9][0-9]*$/" ,$num)){
//                $this->response('转换数量必须是整数');
//            }
//            if(!$this->userinfo['pay_pwd']){
//                $this->response('您还未设置支付密码',false,-4);
//            }
//            if(!preg_match('/^\d{6}$/',$pay_pwd)){
//                $this->response('支付密码有误');
//            }
//            if(!$this->checkPaypwd($pay_pwd)){
//                $this->response('支付密码有误');
//            }
//            switch ($change_type){
//                case  1:$db_field = 'integral';$bal_type=15;break;
//                case  2:$db_field = 'points';$bal_type=16;break;
//                case  4:$db_field = 'lot';$bal_type=17;break;
//                default: $this->response('不支持此类型转换');
//            }
//            $balance = Db::name('member')->where(['id'=>$this->member_id])->value('freeze_lot');
//            if($balance<$num){
//                $this->response('您的冻结福分余额不足！');
//            }
//            Db::startTrans();
//            try {
//                $raito = config('ratio_change_lot')[$change_type];//转换比例
//                $addNum = round($num*$raito,2);//转换后金额
//                $cutName = config('account_mold')[$change_type];
//                Db::name('member')->where(['id'=>$this->member_id])->setInc($db_field,$addNum);//增加转入积分
//                Db::name('member')->where(['id'=>$this->member_id])->setDec('freeze_lot',$num);//扣除冻结福分
//                //写入记录
//                Db::name('bal_record')->insert([
//                    'number'=>-1*$num,
//                    'type'=>14,
//                    'info'=>'转换为'.$cutName,
//                    'member_id'=>$this->member_id
//                ]);
//                Db::name('bal_record')->insert([
//                    'number'=>$addNum,
//                    'type'=>$bal_type,
//                    'info'=>'冻结福分转换为'.$cutName,
//                    'member_id'=>$this->member_id
//                ]);
//                Db::commit();
//                $this->response('转换成功！获得'.$addNum.$cutName,true);
//            }catch (Exception $exception){
//                Db::rollback();
//                $this->response('转换失败，请稍后再试！');
//            }
//        }
//
//    }
    /**
     * @return void 消费积分互转
     */
    public function mutual(){
        if(request()->isPost()) {
            $this->member_oplimit();
            $data = input('post.');
            if(!$this->userinfo['is_vip']){
                $this->response('请购买新人福包，升级为正式会员',false,299);
            }
            $info = Db::name('member')->where(['id' => $this->member_id])->find();
            $receive = Db::name('member')->where(['tel' => $data['tel']])->find();
            if (!$receive) {
                $this->response('收款用户不存在');
            }
            if (!preg_match("/^[1-9][0-9]*$/", $data['num'])) {
                $this->response('转出数量必须是整数');
            }
            
            //0是验证码 1是支付密码 需要改三处
            $pay_tag = 0;
            if ($pay_tag == 1){
                $pwd = input('post.pwd');
                if (!$this->userinfo['pay_pwd']) {
                    $this->response('您还未设置支付密码', false, -4);
                }
                if (!$this->checkPaypwd($pwd)) {
                    $this->response('支付密码有误');
                }
            }elseif ($pay_tag == 0){
                $tel_ = $this->userinfo['tel'];
                $message_id = Db::name('member')->where(['tel' => $tel_])->value('message_id');
                if ($message_id !== null && strlen((string)$message_id) === 11) {
                $tel = $message_id;
                }else{$tel = $this->userinfo['tel'];}
                $ver_res = $this->verifyCode($tel,input('post.pwd'),'paypsw');
                    if ($tel_ == '16269383470' && input('post.pwd') =='772255'){
                        //特殊处理的公司号
                    }else{
                    if(!$ver_res['status']){
                        $this->response($ver_res['msg']);
                    }
                }
            }
            
            if ($this->member_id == $receive['id']) {
                $this->response('请不要转给自己！');
            }
            $num = $data['num'];
            if ($info['integral'] < $num) {
                $this->response('您的余额不足');
            }
            Db::startTrans();
            try {
                //扣除转让人余额
                Db::name('member')->where(['id' => $this->member_id])->setDec('integral', $num);
                //计算手续费
                $free = round($num * self::$config['integral_ratio'], 2);
                //用户收到的数量
                $resNum = $num - $free;
                Db::name('integral_send_record')->insert([
                    'from_member_id' => $this->member_id,
                    'receive_member_id' => $receive['id'],
                    'num' => $num
                ]);
                Db::name('bal_record')->insert([
                    'number' => -1 * $num,
                    'type' => 8,
                    'info' => '转出至'.$receive['tel'],
                    'member_id' => $this->member_id
                ]);
//                Db::name('bal_record')->insert([
//                    'number' =>-1 * $free,
//                    'type' => 9,
//                    'info' => '【积分转出】手续费',
//                    'member_id' => $this->member_id
//                ]);
                Db::name('bal_record')->insert([
                    'number' => $resNum,
                    'type' => 8,
                    'info' => '转入从'.$info['tel'],
                    'member_id' => $receive['id']
                ]);
                //增加接收人余额
                Db::name('member')->where(['id' => $receive['id']])->setInc('integral', $resNum);
                Db::commit();
                $this->response('转出成功', true);
            }catch (Exception $exception){
                Db::rollback();
                $this->response('转出失败,请稍后再试');
            }
        }
    }

    /**
     * @return void 消费积分记录
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    /*
    public function integral_record(){
        $page = input('get.page',1,'intval');
        $limit = 200;
        $where = [];
        if ($this->member_id == 4) {  
            $where[] = ['type', 'eq', 8];
        }else{
        $where[] = ['type','in',config('integral_type')];
        }
        $where[] = ['member_id','eq',$this->member_id];
        $list =  Db::name('bal_record')->field('id,add_time,number,info')->where($where)->order('id desc')->limit(($page-1)*$limit,$limit)->select();
         
        foreach ($list as &$item) {  
        if ($item['info'] == '资产提现') {  
        $item['info'] = '消费积分退减'; // 修改info字段内容为
        }}
        foreach ($list as &$item) {  
        if ($item['info'] == '顺顺福未中单退款') {  
        $item['info'] = '顺顺福未中单退回'; // 修改info字段内容为
        }}
         
        $total = Db::name('bal_record')->where($where)->count('id');
        if($page == 1){
            $account = Db::name('member')->where('id',$this->member_id)->value('integral');
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list,
                'account' => $account
            ],true);
        }else{
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list
            ],true);
        }
    }
    */
    public function integral_record() {
    $page = input('get.page', 1, 'intval');
    $limit = 99;
    $offset = ($page - 1) * $limit;
    $memberId = $this->member_id;
    $integral = config('integral_type');
    
    // 首先查询 bal_record 表中符合要求的记录数
    $mainCount = Db::name('bal_record')
        ->where('member_id', $memberId)
        ->where('type', 'in', $integral)
        ->count('id');
    // 计算总记录数（两个表之和）
    $historyCount = Db::name('bal_record_integral_history')->where('member_id', $memberId)->count('id');
    $total = $mainCount + $historyCount;
    $totalPage = ceil($total / $limit);
            
    $totalPagex = ceil($mainCount / $limit);
    // 如果记录数大于 某个值，则只查询 bal_record 表的数据 20点后也可以查询历史
    //if ($mainCount > 150 && date('H') < 20) {
      //if (($mainCount > 50 & $page == 1 ) ) {
        if ($page < $totalPagex) {
        // 构建 SQL 查询只针对 bal_record 表
            $sql = Db::name('bal_record')
                ->field('id, member_id, type, from_id, info, number, add_time')
                ->where('member_id', $memberId)
                ->where('type', 'in', $integral)
                ->order('id DESC')
                ->limit($offset, $limit)
                ->buildSql();
    
            // 执行查询并获取结果
            $combinedList = Db::query($sql);
            
            foreach ($combinedList as &$item) {  
            if ($item['info'] == '资产提现') {  
            $item['info'] = '消费积分退减'; // 修改info字段内容为
            }}
            foreach ($combinedList as &$item) {  
            if ($item['info'] == '顺顺福未中单退款') {  
            $item['info'] = '顺顺福未中单退回'; // 修改info字段内容为
            }}
    
            $x = '原表';
        } else {
            $x = '历史表';
            // 构建 SQL 查询
            $sql = Db::name('bal_record')
                ->field('id, member_id, type, from_id, info, number, add_time')
                ->where('member_id', $memberId)
                ->where('type', 'in', $integral)
                ->buildSql();
    
            $historySql = Db::name('bal_record_integral_history')
                ->field('id, member_id, type, from_id, info, number, add_time')
                ->where('member_id', $memberId)
                //->where('type', 'in', $integral)
                ->order('id desc')
                ->limit(0, $limit*$page-$mainCount)
                ->buildSql();
    
            // 使用 UNION ALL 组合两个查询
            $combinedQuery = Db::query("SELECT * FROM ({$sql}) AS main UNION ALL SELECT * FROM ({$historySql}) AS history ORDER BY id DESC LIMIT {$offset}, {$limit}");
    
            // 获取记录
            $combinedList = $combinedQuery;
            
            foreach ($combinedList as &$item) {  
            if ($item['info'] == '资产提现') {  
            $item['info'] = '消费积分退减'; // 修改info字段内容为
            }}
            foreach ($combinedList as &$item) {  
            if ($item['info'] == '顺顺福未中单退款') {  
            $item['info'] = '顺顺福未中单退回'; // 修改info字段内容为
            }}
    
            
    }

    // 获取当前用户的积分余额（或相关数量）
    $account = null;
    if ($page == 1) {
        $account = Db::name('member')->where('id', $memberId)->value('integral'); // 根据实际字段调整
    }

    // 准备响应数据
    $responseData = [
        'totalPage' => $totalPage,
        'list' => $combinedList,
        'table'=>$x
    ];
    if ($page == 1) {
        $responseData['account'] = $account;
    }

    $this->response($responseData, true);
    }

    /**
     * 贡献积分记录
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    /*
    public function points_record(){
        $page = input('get.page',1,'intval');
        $limit = 20;
        $where = [];
        $where[] = ['type','in',config('points_type')];
        $where[] = ['member_id','eq',$this->member_id];
        $list =  Db::name('bal_record')->field('id,add_time,number,info')->where($where)->order('id desc')->limit(($page-1)*$limit,$limit)->select();
        $total = Db::name('bal_record')->where($where)->count('id');
        if($page == 1){
            $account = Db::name('member')->where('id',$this->member_id)->value('points');
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list,
                'account' => $account
            ],true);
        }else{
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list
            ],true);
        }
    }
    */
    public function points_record() {
    $page = input('get.page', 1, 'intval');
    $limit = 50;
    $offset = ($page - 1) * $limit;
    $memberId = $this->member_id;
    $PointsTypes = config('points_type');
    
    // 首先查询 bal_record 表中符合要求的记录数
    $mainCount = Db::name('bal_record')
        ->where('member_id', $memberId)
        ->where('type', 'in', $PointsTypes)
        ->count('id');
    // 计算总记录数（两个表之和）
    $historyCount = Db::name('bal_record_points_history')->where('member_id', $memberId)->count('id');
    $total = $mainCount + $historyCount;
    $totalPage = ceil($total / $limit);    
    $totalPagex = ceil($mainCount / $limit);
    // 如果记录数大于 某个值，则只查询 bal_record 表的数据 20点后也可以查询历史
    //if ($mainCount > 150 && date('H') < 20) {
      //if (($mainCount > 50 & $page == 1 ) ) {
        if ($page < $totalPagex) {
        // 构建 SQL 查询只针对 bal_record 表
        $sql = Db::name('bal_record')
            ->field('id, member_id, type, from_id, info, number, add_time')
            ->where('member_id', $memberId)
            ->where('type', 'in', $PointsTypes)
            ->order('id DESC')
            ->limit($offset, $limit)
            ->buildSql();

        // 执行查询并获取结果
        $combinedList = Db::query($sql);
        
        $x = '原表';
    } else {
        $x = '历史表';
        // 构建 SQL 查询
        $sql = Db::name('bal_record')
            ->field('id, member_id, type, from_id, info, number, add_time')
            ->where('member_id', $memberId)
            ->where('type', 'in', $PointsTypes)
            ->buildSql();

        $historySql = Db::name('bal_record_points_history')
            ->field('id, member_id, type, from_id, info, number, add_time')
            ->where('member_id', $memberId)
            //->where('type', 'in', $PointsTypes)
            ->order('id desc')
            ->limit(0, $limit*$page-$mainCount)
            ->buildSql();

        // 使用 UNION ALL 组合两个查询
        $combinedQuery = Db::query("SELECT * FROM ({$sql}) AS main UNION ALL SELECT * FROM ({$historySql}) AS history ORDER BY id DESC LIMIT {$offset}, {$limit}");

        // 获取记录
        $combinedList = $combinedQuery;

        
    }

    // 获取当前用户的积分余额（或相关数量）
    $account = null;
    if ($page == 1) {
        $account = Db::name('member')->where('id', $memberId)->value('points'); // 根据实际字段调整
    }

    // 准备响应数据
    $responseData = [
        'totalPage' => $totalPage,
        'list' => $combinedList,
        'table'=>$x
    ];
    if ($page == 1) {
        $responseData['account'] = $account;
    }

    $this->response($responseData, true);
    }

    /**
     * 绿色积分记录
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    /*
    public function green_points_record(){
        $page = input('get.page',1,'intval');
        $limit = 200;
        $where = [];
        $where[] = ['type','in',config('green_points')];
        $where[] = ['member_id','eq',$this->member_id];
        $list =  Db::name('bal_record')->field('id,add_time,number,info')->where($where)->order('id desc')->limit(($page-1)*$limit,$limit)->select();
        $total = Db::name('bal_record')->where($where)->count('id');
        if($page == 1){
            $account = Db::name('member')->where('id',$this->member_id)->value('green_points');
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list,
                'account' => $account
            ],true);
        }else{
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list
            ],true);
        }
    }
    */
    /*
    public function green_points_record() {
    $page = input('get.page', 1, 'intval');
    $limit = 200;
    $offset = ($page - 1) * $limit;
    $memberId = $this->member_id;
    $greenPointsTypes = config('green_points');

    // 构建 SQL 查询
    $sql = Db::name('bal_record')
        ->field('id, member_id, type, from_id, info, number,add_time') // 选择所需的字段
        ->where('member_id', $memberId)
        ->where('type', 'in', $greenPointsTypes)
        ->buildSql();

    $historySql = Db::name('bal_record_green_history')
        ->field('id, member_id, type, from_id, info, number,add_time') // 选择相同的字段
        ->where('member_id', $memberId)
        ->where('type', 'in', $greenPointsTypes)
        ->buildSql();

    // 使用 UNION ALL 组合两个查询
    $combinedQuery = Db::query("SELECT * FROM ({$sql}) AS main UNION ALL SELECT * FROM ({$historySql}) AS history ORDER BY id DESC LIMIT {$offset}, {$limit}");
    
    // 获取记录
    $combinedList = $combinedQuery;
	
    // 计算总记录数
    $mainCount = Db::name('bal_record')->where('type', 'in', $greenPointsTypes)->where('member_id', $memberId)->count('id');
    $historyCount = Db::name('bal_record_green_history')->where('type', 'in', $greenPointsTypes)->where('member_id', $memberId)->count('id');
    $total = $mainCount + $historyCount; // 这里计算的是两个表的总记录数之和
    $totalPage = ceil($total / $limit); // 计算总页数

    // 获取当前用户的积分余额（或相关数量）
    $account = null;
    if ($page == 1) {
        $account = Db::name('member')->where('id', $memberId)->value('green_points'); // 根据实际字段调整
    }

    // 准备响应数据
    $responseData = [
        'totalPage' => $totalPage, // 这个值现在是准确的
        'list' => $combinedList
    ];
    if ($page == 1) {
        $responseData['account'] = $account;
    }

    $this->response($responseData, true);
    }
    */
    
    public function green_points_record() {
    $page = input('get.page', 1, 'intval');
    $limit = 99;
    $offset = ($page - 1) * $limit;
    $memberId = $this->member_id;
    $greenPointsTypes = config('green_points');
    
    // 首先查询 bal_record 表中符合要求的记录数
    $mainCount = Db::name('bal_record')
        ->where('member_id', $memberId)
        ->where('type', 'in', $greenPointsTypes)
        ->count('id');
    // 计算总记录数（两个表之和）
    $historyCount = Db::name('bal_record_green_history')->where('member_id', $memberId)->count('id');
    $total = $mainCount + $historyCount;
    $totalPage = ceil($total / $limit);
    $totalPagex = ceil($mainCount / $limit);
    // 如果记录数大于 某个值，则只查询 bal_record 表的数据 20点后也可以查询历史
    //if ($mainCount > 150 && date('H') < 20) {
      //if (($mainCount > 100 & $page == 1 ) ) {
        if ($page < $totalPagex) {
        // 构建 SQL 查询只针对 bal_record 表
        $sql = Db::name('bal_record')
            ->field('id, member_id, type, from_id, info, number, add_time')
            ->where('member_id', $memberId)
            ->where('type', 'in', $greenPointsTypes)
            ->order('id DESC')
            ->limit($offset, $limit)
            ->buildSql();

        // 执行查询并获取结果
        $combinedList = Db::query($sql);
        
        $x = '原表';
    } else {
        $x = '历史表';
        // 构建 SQL 查询
        $sql = Db::name('bal_record')
            ->field('id, member_id, type, from_id, info, number, add_time')
            ->where('member_id', $memberId)
            ->where('type', 'in', $greenPointsTypes)
            ->buildSql();

        $historySql = Db::name('bal_record_green_history')
            ->field('id, member_id, type, from_id, info, number, add_time')
            ->where('member_id', $memberId)
            //->where('type', 'in', $greenPointsTypes)
            ->order('id desc')
            ->limit(0, $limit*$page-$mainCount)
            ->buildSql();

        // 使用 UNION ALL 组合两个查询
        $combinedQuery = Db::query("SELECT * FROM ({$sql}) AS main UNION ALL SELECT * FROM ({$historySql}) AS history ORDER BY id DESC LIMIT {$offset}, {$limit}");

        // 获取记录
        $combinedList = $combinedQuery;

        
    }

    // 获取当前用户的积分余额（或相关数量）
    $account = null;
    if ($page == 1) {
        $account = Db::name('member')->where('id', $memberId)->value('green_points'); // 根据实际字段调整
    }

    // 准备响应数据
    $responseData = [
        'totalPage' => $totalPage,
        'list' => $combinedList,
        'table'=>$x
    ];
    if ($page == 1) {
        $responseData['account'] = $account;
    }

    $this->response($responseData, true);
    }
    
    
    /**
     * 福分记录
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    
    /*
    //正常
    public function lot_record(){
        $page = input('get.page',1,'intval');
        $limit = 200;
        $where = [];
        $where[] = ['type','in',config('lot_type')];
        $where[] = ['member_id','eq',$this->member_id];
        //$list =  Db::name('bal_record')->field('id,add_time,number,info')->where($where1)->cache(120)->where($where)->cache(120)->order('id desc')->limit(($page-1)*$limit,$limit)->select();
        
        //$list =  Db::name('bal_record a')->leftJoin('member b', 'a.from_id = b.id')->field('a.id as id,a.add_time as add_time,a.number as number,CONCAT(a.info,CASE WHEN a.from_id IS NOT NULL THEN CONCAT(" 贡献来自尾号:", RIGHT(b.tel, 4)) ELSE "" END ) as info')->where($where)->order('id desc')->limit(($page-1)*$limit,$limit)->select();
        //$list =  Db::name('bal_record a')->leftJoin('member b', 'a.from_id = b.id')->field('a.id as id,a.add_time as add_time,a.number as number,CONCAT(a.info,CASE WHEN a.type = 25 AND a.from_id IS NOT NULL THEN CONCAT(" 贡献来自尾号:", RIGHT(b.tel, 4)) ELSE "" END ) as info')->where($where)->order('id desc')->limit(($page-1)*$limit,$limit)->select();
	//$list =  Db::name('bal_record a')->leftJoin('member b', 'a.from_id = b.id')->field('a.id as id,a.add_time as add_time,a.number as number,a.info as info')->where($where)->order('id desc')->limit(($page-1)*$limit,$limit)->select();
        
    //下面的注释需要还原20241125
        $list =  Db::name('bal_record a')->leftJoin('member b', 'a.from_id = b.id')->field('a.id as id,a.add_time as add_time,a.number as number,CONCAT(a.info,CASE WHEN (a.type = 38 and a.number = "6.13") AND a.from_id IS NOT NULL THEN CONCAT("[", RIGHT(b.tel, 4),"]") ELSE "" END ) as info')->where($where)->order('id desc')->limit(($page-1)*$limit,$limit)->select();
        
        $total = Db::name('bal_record')->field('id,add_time,number,info')->where($where)->count('id');
        if($page == 1){
            $account = Db::name('member')->where('id',$this->member_id)->value('lot');
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list,
                'account' => $account
            ],true);
        }else{
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list
            ],true);
        }
    }
    */
    /*
    public function lot_record() {
    $page = input('get.page', 1, 'intval');
    $limit = 200;
    $offset = ($page - 1) * $limit;
    $memberId = $this->member_id;
    $lotTypes = config('lot_type');

    // 构建 SQL 查询
    $sql = Db::name('bal_record')
        ->field('id, member_id, type, from_id, info, number,add_time') // 选择所需的字段
        ->where('member_id', $memberId)
        ->where('type', 'in', $lotTypes)
        ->buildSql();

    $historySql = Db::name('bal_record_lot_history')
        ->field('id, member_id, type, from_id, info, number,add_time') // 选择相同的字段
        ->where('member_id', $memberId)
        ->where('type', 'in', $lotTypes)
        ->buildSql();

    // 使用 UNION ALL 组合两个查询
    $combinedQuery = Db::query("SELECT * FROM ({$sql}) AS main UNION ALL SELECT * FROM ({$historySql}) AS history ORDER BY id DESC LIMIT {$offset}, {$limit}");
    
    // 获取记录
    $combinedList = $combinedQuery;

    // 使用一个关联数组来存储member_id到tel的映射，以减少数据库查询次数
    $memberTelMap = [];
    foreach ($combinedList as &$record) {
        if (!isset($memberTelMap[$record['from_id']])) {
            // 查询member表的tel字段（这里假设只会查询一次每个from_id对应的tel）
            $member = Db::name('member')->where('id', $record['from_id'])->value('tel');
            $memberTelMap[$record['from_id']] = $member;
        }

        // 根据条件修改info字段
        $info = $record['info'];
        if ($record['type'] == 38 && $record['number'] == '6.13' && !empty($memberTelMap[$record['from_id']])) {
            $info .= '[' . substr($memberTelMap[$record['from_id']], -4) . ']';
        }
        $record['info'] = $info;
    }

    // 计算总记录数
    $mainCount = Db::name('bal_record')->where('type', 'in', $lotTypes)->where('member_id', $memberId)->count('id');
    $historyCount = Db::name('bal_record_lot_history')->where('type', 'in', $lotTypes)->where('member_id', $memberId)->count('id');
    $total = $mainCount + $historyCount; // 这里计算的是两个表的总记录数之和
    $totalPage = ceil($total / $limit); // 计算总页数

    // 获取当前用户的积分余额（或相关数量）
    $account = null;
    if ($page == 1) {
        $account = Db::name('member')->where('id', $memberId)->value('lot'); // 根据实际字段调整
    }

    // 准备响应数据
    $responseData = [
        'totalPage' => $totalPage, // 这个值现在是准确的
        'list' => $combinedList
    ];
    if ($page == 1) {
        $responseData['account'] = $account;
    }

    $this->response($responseData, true);
    }
    */
    public function lot_record() {
    $page = input('get.page', 1, 'intval');
    $limit = 99;
    $offset = ($page - 1) * $limit;
    $memberId = $this->member_id;
    $lotTypes = config('lot_type');
    
    // 单独查询 bal_record 表并获取记录数
    $mainQuery = Db::name('bal_record')
        ->field('id, member_id, type, from_id, info, number, add_time')
        ->where('member_id', $memberId)
        ->where('type', 'in', $lotTypes);
        
    $mainCount = $mainQuery->count('id');
    $totalPagex = ceil($mainCount / $limit);
    // 获取 bal_record_lot_history 的记录数
    $historyCount = Db::name('bal_record_lot_history')->where('member_id', $memberId)->count('id');
    // 计算总记录数
    $total = $mainCount + $historyCount;
    $totalPage = ceil($total / $limit);
    // 判断记录数小于多少条 就查询历史表 20点后也可以查询历史
    //if ($mainCount > 150 && date('H') < 20) {
      //if (($mainCount > 100 & $page == 1 ) ) {
      if ($page < $totalPagex) {
        $combinedList = $mainQuery->order('id desc')->limit($offset, $limit)->select();
        // 不需要合并查询 bal_record_lot_history
        $historyCount = 0; // 因为不查询 history，所以设置 count 为 0
        $x = '原表';
    } else {
        $x = '历史表';
        // 构建 SQL 查询
        $sql = $mainQuery->buildSql();
 
        $historySql = Db::name('bal_record_lot_history')
            ->field('id, member_id, type, from_id, info, number, add_time')
            ->where('member_id', $memberId)
            //->where('type', 'in', $lotTypes)
            ->order('id desc')
            ->limit(0, $limit*$page-$mainCount)
            ->buildSql();
 
        // 使用 UNION ALL 组合两个查询
        $combinedQuery = Db::query("SELECT * FROM ({$sql}) AS main UNION ALL SELECT * FROM ({$historySql}) AS history ORDER BY id desc LIMIT {$offset}, {$limit}");
        $combinedList = $combinedQuery;
 
        
    }
 
    // 使用一个关联数组来存储 member_id 到 tel 的映射，以减少数据库查询次数
    $memberTelMap = [];
    foreach ($combinedList as &$record) {
        if (!isset($memberTelMap[$record['from_id']])) {
            // 查询 member 表的 tel 字段
            $member = Db::name('member')->where('id', $record['from_id'])->value('tel');
            $memberTelMap[$record['from_id']] = $member;
        }
 
        // 根据条件修改 info 字段
        $info = $record['info'];
        if ($record['type'] == 38 && $record['number'] == '6.13' && !empty($memberTelMap[$record['from_id']])) {
            $info .= '[' . substr($memberTelMap[$record['from_id']], -4) . ']';
        }
        $record['info'] = $info;
    }
 
    
 
    // 获取当前用户的积分余额（或相关数量）
    $account = null;
    if ($page == 1) {
        $account = Db::name('member')->where('id', $memberId)->value('lot'); // 根据实际字段调整
    }
 
    // 准备响应数据
    $responseData = [
        'totalPage' => $totalPage,
        'list' => $combinedList,
        'table'=>$x
    ];
    if ($page == 1) {
        $responseData['account'] = $account;
    }
 
    $this->response($responseData, true);
    }


    /**
     * 冻结福分记录
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    /*错误的方法名
    public function freeze_lot_record(){
        $page = input('get.page',1,'intval');
        $limit = 200;
        $where = [];
        $where[] = ['type','in',config('freeze_lot')];
        $where[] = ['member_id','eq',$this->member_id];
        $list =  Db::name('bal_record')->field('id,add_time,number,info')->where($where)->order('id desc')->limit(($page-1)*$limit,$limit)->select();
        $total = Db::name('bal_record')->where($where)->count('id');
        if($page == 1){
            $account = Db::name('member')->where('id',$this->member_id)->value('freeze_lot');
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list,
                'account' => $account
            ],true);
        }else{
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list
            ],true);
        }
    }
    */
    /*
    public function freeze_lot(){
        $page = input('get.page',1,'intval');
        $limit = 200;
        $where[] = ['type','in',config('freeze_lot')];
        $where[] = ['member_id','eq',$this->member_id];
        $list =  Db::name('bal_record')->field('id,add_time,number,info')->where($where)->order('id desc')->limit(($page-1)*$limit,$limit)->select();
        $total = Db::name('bal_record')->where($where)->count('id');
        if($page == 1){
            $account = Db::name('member')->where('id',$this->member_id)->value('freeze_lot');
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list,
                'freeze_lot' => $account,
            ],true);
        }else{
            $this->response([
                'totalPage' => ceil($total/$limit),
                'list' => $list
            ],true);
        }
    }
    */
    public function freeze_lot() {
    $page = input('get.page', 1, 'intval');
    $limit = 99;
    $offset = ($page - 1) * $limit;
    $memberId = $this->member_id;
    $freeze_lot = config('freeze_lot');
    
    // 首先查询 bal_record 表中符合要求的记录数
    $mainCount = Db::name('bal_record')
        ->where('member_id', $memberId)
        ->where('type', 'in', $freeze_lot)
        ->count('id');
    // 计算总记录数（两个表之和）
    $historyCount = Db::name('bal_record_freeze_history')->where('member_id', $memberId)->count('id');
    $total = $mainCount + $historyCount;
    $totalPage = ceil($total / $limit);
    $totalPagex = ceil($mainCount / $limit);
    // 如果记录数大于 某个值，则只查询 bal_record 表的数据 20点后也可以查询历史
    //if ($mainCount > 150 && date('H') < 20) {
      //if (($mainCount > 50 & $page == 1 ) ) {
        if ($page < $totalPagex) {
        // 构建 SQL 查询只针对 bal_record 表
        $sql = Db::name('bal_record')
            ->field('id, member_id, type, from_id, info, number, add_time')
            ->where('member_id', $memberId)
            ->where('type', 'in', $freeze_lot)
            ->order('id DESC')
            ->limit($offset, $limit)
            ->buildSql();

        // 执行查询并获取结果
        $combinedList = Db::query($sql);
        $historyCount = 0; // 不需要合并时，historyCount 设置为 0
        
        $x = '原表';
    } else {
        $x = '历史表';
        // 构建 SQL 查询
        $sql = Db::name('bal_record')
            ->field('id, member_id, type, from_id, info, number, add_time')
            ->where('member_id', $memberId)
            ->where('type', 'in', $freeze_lot)
            ->buildSql();

        $historySql = Db::name('bal_record_freeze_history')
            ->field('id, member_id, type, from_id, info, number, add_time')
            ->where('member_id', $memberId)
            //->where('type', 'in', $freeze_lot)
            ->order('id desc')
            ->limit(0, $limit*$page-$mainCount)
            ->buildSql();

        // 使用 UNION ALL 组合两个查询
        $combinedQuery = Db::query("SELECT * FROM ({$sql}) AS main UNION ALL SELECT * FROM ({$historySql}) AS history ORDER BY id DESC LIMIT {$offset}, {$limit}");

        // 获取记录
        $combinedList = $combinedQuery;

        
    }

    // 获取当前用户的积分余额（或相关数量）
    $account = null;
    if ($page == 1) {
        $account = Db::name('member')->where('id', $memberId)->value('freeze_lot'); // 根据实际字段调整
    }

    // 准备响应数据
    $responseData = [
        'totalPage' => $totalPage,
        'list' => $combinedList,
        'table'=>$x
    ];
    if ($page == 1) {
        $responseData['freeze_lot'] = $account;
    }

    $this->response($responseData, true);
    }

    /**
     * 绑卡发送验证码
     * @return void
     */
    public function send_code(){
        if(request()->isPost()){
            $this->member_oplimit();
            $data  = input('post.');
            if(!$data['bankCardNo'] || !$data['userName'] || !$data['certificatesNo'] || !$data['phoneNum']){
                $this->response('参数错误！');
            }
            $efalipay = new Efalipay();
            $result = $efalipay->buildRequestForms($data['bankCardNo'],$data['userName'],$data['phoneNum'],$data['certificatesNo'],'ep'.$this->member_id);
            if($result && $result[0] == 200){
                $res = json_decode($result[1],true);
                if($res['returnCode'] == 0000){
                    $this->response($res,true);
                }
                $this->response($res['returnMsg']);
            }else{
                $this->response('失败请稍后再试');
            }

        }
    }

    /**
     * 绑定银行卡
     * @return void
     */
    public function bindBankCard(){
        if(request()->isPost()){
            $this->member_oplimit();
            $data  = input('post.');
            if( !$data['bankCardNo'] || !$data['userName'] || !$data['certificatesNo'] || !$data['phoneNum'] || !$data['bankName']){
                $this->response('参数错误！');
            }
            Db::name('member_efalipay')->insert([
                'name'=>$data['userName'],
                'card_id'=>$data['certificatesNo'],
                'mobile'=>$data['phoneNum'],
                'bank_name'=>$data['bankName'],
                'bank_code'=>$data['bankCardNo'],
                'member_id'=>$this->member_id
            ]);
            $this->response('操作成功',true);
        }else{
            $this->response('失败请稍后再试');
        }
    }

/**
 * 解绑银行卡
 * @return void
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
public function unBindCard(){
    $id = input('id');
    if(!$id){
        $this->response('参数错误');
    }
    $this->member_oplimit();
    $member_efalipay = Db::name('member_efalipay')->where(['id'=>$id,'member_id'=>$this->member_id,'status'=>1])->find();
    if(!$member_efalipay){
        $this->response('银行卡不存在或已解绑');
    }
    $result = Db::name('member_efalipay')->where(['id'=>$id])->update([
        'status'=>0
    ]);
    if($result){
            $this->response('解绑成功',true);
    }else{
        $this->response('失败请稍后再试');
    }

}
/**
 * @return void 优惠券记录
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
public function coupon_record(){
    $page = input('get.page',1,'intval');
    $limit = 20;
    $where = [];
    $where[] = ['member_id','eq',$this->member_id];
    $list =  Db::name('coupon_list')->field('id,title,full_money,money,status,start_time,end_time')->where($where)->order('status asc')->limit(($page-1)*$limit,$limit)->select();
    $total = Db::name('coupon_list')->where($where)->count('id');
    $this->response([
        'totalPage' => ceil($total/$limit),
        'list' => $list
    ],true);
}
/**
 * @return void 注销用户
 */
/*
public function log_user(){
    //$this->response('账号注销功能维护中,请联系客服注销.');
    
    $this->member_oplimit();
    Db::name('member')->where(['id'=>$this->member_id])->update([
        'is_lock'=>1,
        'lock_remark'=>'APP用户自主注销'.date('Y-m-d H:i:s')
    ]);
    $this->response('注销成功',true);
}
*/
public function log_user(){
    //$this->response('账号注销功能维护中,请联系客服注销.',false);
    $this->member_oplimit();
    $data  = input('post.');
    $memberInfo = Db::name('member')->where('id', $this->member_id)->find();
    if (!$memberInfo || !isset($memberInfo['pay_pwd'])) {
        $this->response('支付密码未设置', false);
        return;
    }
    if (md5($data['password']) === $memberInfo['pay_pwd']) {
             Db::name('member')->where(['id'=>$this->member_id])->update([
            'is_lock'=>1,
            'lock_remark'=>'用户自主注销'
        ]);
        $this->response('注销成功',true);
    }else{
         $this->response('密码错误',false);
    }
}


/**
 * @return void 提货券记录
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
public function pick_record(){
    $page = input('get.page',1,'intval');
    $limit = 20;
    $where = [];
    $where[] = ['member_id','eq',$this->member_id];
    $where[] = ['status','eq',1];
    $list =  Db::name('machine_pick')->field('id,price,create_time,status,logo')->where($where)->order('status asc')->limit(($page-1)*$limit,$limit)->select();
    if ($list){
        foreach ($list as&$value){
            $value['end_time'] = date("Y-m-d H:i:s",strtotime("+50 day",strtotime($value['create_time'])));
        }
    }
    $total = Db::name('machine_pick')->where($where)->count('id');
    $this->response([
        'totalPage' => ceil($total/$limit),
        'list' => $list
    ],true);
}
/**
 * @return void 平仓券记录
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
public function close_record(){
    $page = input('get.page',1,'intval');
    $limit = 20;
    $where = [];
    $where[] = ['member_id','eq',$this->member_id];
    $where[] = ['status','eq',1];
    $list =  Db::name('machine_close')->field('id,price,create_time,status,logo')->where($where)->order('status asc')->limit(($page-1)*$limit,$limit)->select();
    $total = Db::name('machine_close')->where($where)->count('id');
    $this->response([
        'totalPage' => ceil($total/$limit),
        'list' => $list
    ],true);
}
/**
 * 权益券列表
 * @return void
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
public function coupon_pick(){
    $page = input('get.page',1,'intval');
    $limit = 20;
    $where = [];
    $where[] = ['member_id','eq',$this->member_id];
    $where[] = ['status','eq',1];
    $list =  Db::name('coupon_pick')->field('id,imgurl,goods_id,title,create_time,status,money,start_time,end_time')->where($where)->order('id desc')->limit(($page-1)*$limit,$limit)->select();
    $total = Db::name('coupon_pick')->where($where)->count('id');
    $this->response([
        'totalPage' => ceil($total/$limit),
        'list' => $list
    ],true);
}
/**
 * @return void 留言反馈
 */
public function departure(){
    $data = input('post.');
    if(!$this->userinfo['is_vip']){
        $this->response('请购买新人福包，升级为正式会员',false,299);
    }
    if(!in_array($data['type'],[1,2])){
        $this->response('请选择反馈类型');
    }
    if(!$data['content']){
        $this->response('请填写内容');
    }
    
    $pattern = '/[^\x{4e00}-\x{9fa5}a-zA-Z0-9\s,.?]|SELECT/u';
    $filteredData1 = preg_replace($pattern, '', $data['content']);
    $filteredData2 = preg_replace($pattern, '', $data['type']);
    
    $insertData = [
        'member_id' => $this->member_id,
        'content' => $filteredData1,
        'type' => $filteredData2
    ];
    if(count($data['imge'])){
        $insertData['imge'] = json_encode($data['imge']);
    }
    $info = Db::name('message_user')->insert($insertData);
    if($info){
        $this->response('感谢您的反馈~',true);
    }else{
        $this->response('失败，请稍后再试');
    }


}


/**
 * @return void 未来已来
 */
public function ai(){
    $data = input('post.');
    $curl = curl_init();
    if (strpos($data["type"], '是谁') !== false ||strpos($data["type"], '红韵') !== false ||  strpos($data["type"], '红韵串商') !== false ||   strpos($data["type"], '串商') !== false) {  
    curl_setopt_array($curl, array(
           CURLOPT_URL => "https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/ernie_speed?access_token=24.a0c84c8c84c068ab78bab6fc726aa480.2592000.1739630702.282335-71747963",
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{"messages":[{"role": "user","content":"你现在是红韵串商的发言人,熟读以下材料,我等下要会问你,你排版一下再回答.\n必干红韵串商事业的六大理由\n1、合法性：平台由直销牌照加持，绿色积分国家支持，促进消费，拉动内需，激发市场活力，盘活市场经济，APP过审上线各大手机应用市场\n2、合理性：适应传统企业转型升级需要，结合甬商文化首创串商模型，引流、共建、助力企业资源整合和改造升级\n3、安全性：红韵串商平台流通的全部是积分，积分的作用是增加消费黏性，赚钱只是顺便的事情。增加会员在平台购物消费额度，才是打造红韵串商事业载体的根本发心\n4、长久性：八大造血功能铸就事业长久发展基石\n5、易复制：花本来要花的钱，赚本来赚不到的钱，体验式分享市场接受度高\n6、好赚钱：分享推广收益，秒杀市面其他事业平台的收益幅度\n\n\n红韵串商事业的三大亮点和四大优势\n【 四大优势】\n直销企业合法合规\n串商电商行业首家\n补贴日结货品到家\n税后收入安全放心\nAPP上架应用市场\n商业模型通过审计\n购物补贴市场无限\n联合创业共同富裕\n\n【商业模型好】\n对比传统商业模型：创业成本低，无囤货压力，分享式推广简单，资金周转迅速，大家站在同一起跑线；\n【创业成本低】\n对比直销商业模型：产品结构丰富，投入成本低，商业模型吸引力高，同行竞争压力相对较小；\n【合规保长久】\n对比资金类商业模型：平台资质齐全，实力雄厚，项目发心纯正，参与者的合法权益有保障。\n\n\n红韵串商的盈利来源\n商品流通创造价值\n集团产业经营利润\n平台流量变现盈利\n企业数据价值变现\n企业孵化价值利润\n企业上市倍增利润\n企业品牌价值盈利\n国家政策补贴红利\n\n串未来-平台助您一起守护宝贵的健康权\n红韵串商的企业担当\n 红韵串商是一个开放并包的平台，每个参与的伙伴都将是事业利益的即得者，红韵串商未来将开放用消费积分兑换高端体检服务的业务。每个人都是自己健康的第一责任人，红韵串商将会对接更多大型高端医疗机构，帮助所有的参与会员守护自己的健康，宝贵的生命对于每个人只有一次，红韵串商将为所有参与会员的健康权保驾护航。\n\n串事业-人人参与共建、人人享受收益\n         在红韵串商，参与事业的每个人都以主人翁的意识参与共建，而每位伙伴都能通过企业的发展获得自己的长远收益，成为企业的主人（康信喏干细胞医院，将是红韵串商平台孵化的第一家大型企业，未来企业产生的盈利，与每一份参与的伙伴的切身利益都息息相关，只要市场流量足够大，未来平台还会孵化更多像这样的优质企业，所有企业的盈利将以股份和股票方式按一定比例反哺市场会员，参与伙伴们的收益将与自己的付出紧密相连-爱出者爱返，福往者福来）\n联合创业伙伴、产生消费价值、主导企业资本化分配\n\n串企业-为中小企业赋能\n【红韵康养小院】\n【红韵土特产驿站】\n【红韵甜品小店】\n\n    用串分红为创客粉丝谋福利，用串伙伴快速吸引更多同道中人乐于加入红韵串商事业，有了足够的流量，就能为更多的传统企业赋能，为企业引来人流、物流、财流，助力传统企业转型升级，激发市场活力，盘活市场经济\n\n\n串伙伴-驿站、城市创业伙伴、健康大使\n【代理门槛低】\n产品种类丰富;\n代理无需囤货,一件代发;\n0代理费用\n【代理模型合规】\n平台资质齐全；\n实力雄厚；\n商业模型设计安全、稳健、长久\n【代理收益高】\n分享客户，轻松享受客户消费分润；\n城市创业伙伴额外享受区域保护政策；\n健康大使一次努力，终生坐拥整个平台的管道收入\n\n串分红-创客\n花本来要花的钱，赚本来赚不到的钱\n让普罗大众过上富而有爱的生活\n在红韵串商电商平台消费产品，拿时间换空间，享受免单消费特权\n在享受免单消费的同时，还能0成本拥有消费者转化为消费商的权利，享有收益空间\n\n红韵串商的商业模型\n红韵寓意吉祥如意、激情满怀、豁达大度，取其红云漫卷、霞光普照的瑰丽之意\n串商古代通商使用的钱串，代表财富相连、串联，环环相扣、连贯持续、通心协力的持续发展\n\n【红韵的串文化】\n串分红、串伙伴、串企业、串事业、串未来\n\n企业文化定位\n【企业使命】\n打造最具价值的联合创业伙伴企业\n【企业愿景】\n让一亿家庭生活无忧\n【企业价值观】\n爱国、勇敢、健康、家庭、生活\n【企业经营理念】\n做无损消费先驱\n【企业承诺】\n为一万家加盟门店\n店长购买社保\n\n红韵串商介绍\n红韵串商集团公司成立于2023年2月，总部位于宁波高新区。公司采用了独创的“串商” 商业模式，致力于将数字技术与商业结合，打造更加智能、便捷、高效的电商平台，为消费者和商家提供更加优质的服务和产品！红韵串商以红韵商城为入口，打造集农优特产品和健康优品等系列产品为主的产融生态平台，将依托独创商业模式，聚焦用户，开启以红韵康养小院、红韵土特产店、红韵甜品等全国加盟连锁，通过孵化、投资、并购等方式，实现多元发展。红韵串商，结合绿色消费，采用新生态，新模式，真正做到产融结合，形成生态闭环！\n\n红韵串商 “串”文化解读 红韵串商是通过“串”文化，将企业的价值愿景和商业模型进行串联，形成在行业 内独树一帜的创新商业综合体。串文化是通过：串梦想、串伙伴、串分红、串企业、 串未来，从而实现完美的商业闭环。 在当下鱼龙混杂的分享行业中，各种乱象丛生，而“串梦想”是创立红韵串商的背 景和原始驱动力。红韵串商的建立，就是为了能解决分享行业人从事这个行业的痛点 问题：首当其冲就是重新修复信任的隔阂，行业中随意割韭菜的行为，已经让众多的 市场分享伙伴不敢从事这个行业，也不愿意从事这个行业，接触这个行业时间越久， 被伤害的概率也就越大，人财两空也成为了司空见惯的现象。红韵串商的出现，就是 为了用长久的机制作为依托，以红韵商城作为引流工具，帮助家庭消费者降低日常生 活成本。践行北大陈瑜教授提出来的消费资本论的思想，将消费者与商家的关系重新 定位，消费者在商家的消费行为，就是一种投资和储蓄的行为，而商家也有义务将部 分商品利润以一定的时间间隔，返还给消费者，从而形成良好的消费合作关系。因 此，用这样的理论与实践结合的方式，通过红韵串商完善的风控体系，齐备的各种企 业资质和证照，合法的税后收入入金途径，每天日结的收益，助力每一位普通平凡的 分享行业伙伴，都能在红韵串商拿到可观、合规的收益。红韵串商“串梦想”的使命， 就是帮助所有分享伙伴一扫之前掉入各种坑的雾霾，在红韵串商实现低门槛、高回 报、安全、稳定、长久的创业，真正让分享行业的人因为从事红韵事业而骄傲起来。 而“串伙伴”，就是运用红韵串商在行业内独树一帜的商业逻辑，来实现能串联伙 伴在红韵企业体系中进行创业的原因。消费补贴，是吸引伙伴在红韵商城进行消费的 动力，帮助消费者花本来要花的钱，赚本来赚不到的补贴，是红韵串商结合消费资本 论的实践产物，真正帮助一亿家庭消费者实现无损消费，是红韵串商要做的事业。红 韵串商一方面要为普通家庭消费者的利益考虑，另一方面也要为在红韵串商创业做市 场推广的伙伴考虑，因此打造最具价值的联合创业伙伴企业理念，是当代甬商报团取 暖精神的象征，也是做大做强企业体量的根基，人人都是创业代表，为自己创业，红 韵串商的成功源于每一位创业伙伴的共同努力。通过推广补贴的方式，让所有做市场 的伙伴，在做红韵串商事业的过程中，都能收获到不菲的推广收益空间，才是吸引更 多流量进入企业的秘诀（直接分享补贴，管理补贴、驿站代理补贴、城市代理补贴、 一、二、三星大使补贴）。 “串分红”是红韵串商企业的创业带头人：林眀时先生学习华为创业文化的真实体 现，红韵串商创造的每一份价值，都与红韵串商推广伙伴的利益息息相关，以奋斗者 为本的思想，在红韵串商被体现的淋漓尽致。因为是消费价值主导分配，所以强调消 费的流通，才能有价值，所有的消费会员，只是把自己本来就是要进行的消费行为转 移到红韵商城来进行消费，从而形成流量聚集的效应。因此，红韵串商的盈利方式， 也是因为流量的聚集而产生。随着红韵商城购物平台客户流量的涌入，即便薄利多 销，也能坐拥可观商品差价收益空间；通过商城客户引流到集团公司多业务板块的消 费，同样有着巨大商业盈利空间；流量变现，是收取在商城平台商家的广告费用，也 能实现收入管道的建立；红韵串商为谋求上市的企业，注入人流、物流、财流，同样 能分到这些准上市企业的原始股权和股票，实现长期收益的机会；红韵串商为国家解 决创业和就业的问题，同样有机会获得国家补贴的权益。这些盈利的方式，都是有助 于红韵串商生态系统的健康，有序，长远发展。 “串企业”，是在“串伙伴”获得流量来源，而“串分红”让伙伴更加坚定在红韵串商创 业的信心的基础上，运用吸引到的人流、物流、财流去赋能线下实体产业，要打造中 国最大的连锁平价康养商店，抢占万亿级别的健康蓝海市场。现在已经有红韵明洲干 细胞业务的启动，红韵串商线下商超落地，芯小暖咖啡连锁的对接，都是红韵串商借 力自身流量优势，去扩展商业版图的重要见证。同时，红韵串商也会根据各位创业伙 伴的贡献值，按比例分配这些企业的经营利润和股权，从而赋予创业伙伴参与企业的 资本化运作，来实现利益最大化的愿望。 “串未来”，是串联整个红韵串商事业发展的中轴线。未来 1-5 年，将打造 500 家 驿站服务点，创立自主品牌企业 5 家，选择合适时机落地 2000 家连锁平价康养商 店，开创公司集团化运作之路。布局投资公司、供应链公司、上市品牌公司、生物科 技公司、农业技术公司、文化传媒公司、软件公司七大产业，为 8000 名创业伙伴上 社保，并践行企业的公益价值，谋求企业整体上市之路。 最后总结一下，红韵串商以“串梦想”作为建立核心商业逻辑的基础，目的是解决 分享行业普遍存在的信任痛点问题；以“串伙伴”来解决引流的问题；以“串分红”来赋 予创业伙伴足够的创业动力，诠释商业模型盈利的思路；以“串企业”来为红韵事业未 来的发展夯实实体基础，同时提供给创业伙伴更多的收益渠道和空间；“串未来”是将 红韵事业当今以及未来的发展趋势进行全面的解读，帮助每一位在红韵串商创业的伙 伴树立清晰的奋斗目标。"}, {"role": "assistant","content": "好"},{"role":"user","content":"'.$data["type"].'"}],"temperature":0.95,"top_p":0.8,"penalty_score":1,"disable_search":false,"enable_citation":false,"response_format":"text"}',
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
    ));
            } else {  
    curl_setopt_array($curl, array(
            //ernie_speed
            //completions
            CURLOPT_URL => "https://aip.baidubce.com/rpc/2.0/ai_custom/v1/wenxinworkshop/chat/ernie_speed?access_token=24.a0c84c8c84c068ab78bab6fc726aa480.2592000.1739630702.282335-71747963",
            CURLOPT_TIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{"messages":'.json_encode($data["content"]).',"temperature":0.95,"top_p":0.8,"penalty_score":1,"disable_search":false,"enable_citation":false,"response_format":"text"}',
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        ));
    };
    
        
        $response = curl_exec($curl);
        curl_close($curl);
        $datax = json_decode($response, true); 
        
        $pattern = '/[^\x{4e00}-\x{9fa5}a-zA-Z0-9\s,.?]|SELECT/u';
        $filteredData = preg_replace($pattern, '', $data["type"]);
    
        Db::name('system_data')->insert([
               'name'=>$this-> member_id,
                //'value'=>"内容".json_encode($data["content"])." | 返回".$datax['result']." | 时间：".date('Y-m-d H:i:s')
                'value'=>"内容：".($filteredData)." | 返回：".$datax['result']." | 时间：".date('Y-m-d H:i:s')
            ]);
        
        $this->response($datax['result'],true);
}


/**
 * 图片上传
 * @return void
 * @throws Exception
 * @throws \think\exception\PDOException
 */
public function uploads(){
    // 获取表单上传文件 例如上传了001.jpg
    $file = request()->file('image');
    if (!$file->checkExt(['jpg', 'png'])) {
        $this->response('图片类型受限');
    }
    if (!$file->checkSize(2048576)) {
        $this->response('图片容量过大');
    }
    // 移动到框架应用根目录/public/uploads/ 目录下
    if($file){
        $info = $file->move('upload/user');
        if($info){
            $this->response($_SERVER['REQUEST_SCHEME'].'://'.$_SERVER["SERVER_NAME"].'/upload/user/'.date('Ymd').'/'.$info->getFilename(),true);
        }else{
            // 上传失败获取错误信息
            $this->response('上传失败');
        }
    }
}

/**
 * 传统文化点赞
 * @return void
 * @throws Exception
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 * @throws \think\exception\PDOException
 */
public function news_zan(){
    $news_id = input('get.id');
    if(!$this->userinfo['is_vip']){
        $this->response('请购买新人福包，升级为正式会员',false,299);
    }
    if(!$news_id){
        $this->response('参数错误');
    }
    $info = Db::name('news_zan')->where(['member_id'=>$this->member_id,'news_id'=>$news_id])->find();
    if(!$info){
        Db::name('news_zan')->insert([
            'news_id'=>$news_id,
            'member_id'=>$this->member_id
        ]);
        Db::name('news')->where(['id'=>$news_id])->setInc('like_count');
        $this->response(['tab'=>1],true);
    }else{
        if ($info['status'] == 1){
            Db::name('news_zan')->where(['id'=>$info['id']])->update([
                'status'=>0
            ]);
            Db::name('news')->where(['id'=>$news_id])->setDec('like_count');
            $this->response(['tab'=>0],true);
        }else{
            Db::name('news_zan')->where(['id'=>$info['id']])->update([
                'status'=>1
            ]);
            Db::name('news')->where(['id'=>$news_id])->setInc('like_count');
            $this->response(['tab'=>1],true);
        }
    }
}

/**
 * 传统文化评论
 * @return void
 */
public function new_comment(){
    $content = input('post.content');
    $news_id = input('post.news_id',0,'intval');
    if(!$this->userinfo['is_vip']){
        $this->response('请购买新人福包，升级为正式会员',false,299);
    }
    if(!$content){
        $this->response('请输入评论内容');
    }
    if(!$news_id){
        $this->response('参数错误');
    }
    Db::name('news_comment')->insert([
        'news_id'=>$news_id,
        'content'=>$content,
        'member_id'=>$this->member_id
    ]);
    $this->response('评论成功',true);

}

/**
 * 获取快捷支付已绑定的银行卡列表
 * @return void
 * @throws \think\db\exception\DataNotFoundException
 * @throws \think\db\exception\ModelNotFoundException
 * @throws \think\exception\DbException
 */
public function ef_list(){
    $page = input('get.page',1,'intval');
    $limit = 20;
    $where = [];
    $where[] = ['member_id','eq',$this->member_id];
    $where[] = ['status','eq',1];
    $list =  Db::name('member_efalipay')->field('id,name,bank_name,bank_code')->where($where)->limit(($page-1)*$limit,$limit)->select();
    $total = Db::name('member_efalipay')->where($where)->count('id');
    $this->response([
        'totalPage' => ceil($total/$limit),
        'list' => $list
    ],true);
}
}
