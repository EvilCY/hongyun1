<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2020/4/21
 * Time: 15:50
 */

namespace app\index\controller;


use AlibabaCloud\Cloudwf\V20170328\DelUmengPagePermission4Root;
use app\lib\AES;
use app\lib\Efalipay;
use app\lib\JsPay;
use app\lib\JytPay\JytJsonClient;
use app\lib\JytCardPay\JytJsonClient as JytCarJsonClient;
use app\lib\PayUtils;
use app\lib\SanDe\api\PCCashier;
use app\lib\WxPay;
use app\lib\YunZhong;
use Lib\Bocpay;
use ShengPayConfig;
use think\Db;
use think\Log;
use think\Request;
use think\Response;
use alipay\aop\AopClient;
use alipay\aop\request\AlipayTradeAppPayRequest;
use alipay\aop\request\AlipayTradeQueryRequest;
use function JytPay\CallBack\accept_call_back;


class Payment
{

    static function alipay_param($amount,$order_sn,$subject,$body,$notify_url)
    {
        $alipay = Db::name('config')->column('val','key');
        $aop = new AopClient ();
        $aop->gatewayUrl = $alipay['gatewayUrl'];
        $aop->appId = $alipay['appId'];
        $aop->rsaPrivateKey =$alipay['rsaPrivateKey'];
        $aop->alipayrsaPublicKey = $alipay['alipayrsaPublicKey'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';
        $aop->format='json';
        $out_trade_no = $order_sn;
        $request = new AlipayTradeAppPayRequest();
        $request->setNotifyUrl($notify_url);
        $request->setBizContent("{\"body\":\"".$body."\","
            . "\"subject\": \"".$subject."\","
            . "\"out_trade_no\": \"".$out_trade_no."\","
            . "\"timeout_express\": \"30m\","
            . "\"total_amount\": \"".round($amount,2)."\","
            . "\"product_code\":\"QUICK_MSECURITY_PAY\""
            . "}");
        $result = $aop->sdkExecute ($request);
        return $result;
    }
    /*
  *微信支付
  **/
    //Jyt支付
    static function jyt_pay($payChannel,$payMode,$orderId,$totalAmt,$subject,$notifyUrl,$spbillCreatIp){
        $client = new JytJsonClient();
        $client->init();
        $data['head']['version']='2.0.0';
        $data['head']['tranType']='01';
        $data['head']['merchantId']= $client->config->merchant_id;
        $data['head']['tranTime']=date('YmdHis',time());
        $data['head']['tranFlowid']= $client->config->merchant_id . date('YmdHis',time()) . substr(rand(),4);
        $data['head']['tranCode']= 'OP1001';
        $data['head']['respCode']='';
        $data['head']['respCesc']='';
        // 报文体
        $data['body']['payChannel'] = $payChannel;
        $data['body']['payMode'] = $payMode;
        $data['body']['orderId'] = $orderId;
        $data['body']['totalAmt'] = $totalAmt;
        $data['body']['subject'] = $subject;
        $data['body']['notifyUrl'] = $notifyUrl;
        $data['body']['spbillCreatIp'] = $spbillCreatIp;
        $res = $client->sendReq($data);
        return $res;
    }

    //JYT快捷支付-支付请求鉴权
    static function jyt_card_pay($custNo,$orderId,$bankCardNo,$idCardNo,$mobile,$name,$totalAmt){
        $client = new JytCarJsonClient();
        $client->init();
        $data['head']['version']='1.0.1';
        $data['head']['tranType']='01';
        $data['head']['merchantId']= $client->config->merchant_id;
        $data['head']['tranDate']=date('Ymd',time());
        $data['head']['tranTime']=date('His',time());
        $data['head']['tranFlowid']= $client->config->merchant_id . date('YmdHis',time()) . substr(rand(),4);
        $data['head']['tranCode']= 'TD1004';
        $data['head']['respCode']='';
        $data['head']['respCesc']='';
        // 报文体
        $data['body']['custNo']= $custNo;//客户编号
        $data['body']['orderId']= $orderId; //自动生成
        $data['body']['bankCardNo']= $bankCardNo; //银行卡号
        $data['body']['idCardNo']= $idCardNo; //身份证
        $data['body']['mobile']= $mobile; //手机号
        $data['body']['name']=$name;
        $data['body']['tranAmt']=$totalAmt;
        $res = $client->sendReq($data);
        return $res;
    }

    //Jyt快捷支付-支付消费交易
    static function jyt_card_pay_do($custNo,$orderId,$bankCardNo,$idCardNo,$mobile,$name,$totalAmt,$verifyCode){
        $client = new JytCarJsonClient();
        $client->init();
        $data['head']['version']='1.0.1';
        $data['head']['tranType']='01';
        $data['head']['merchantId']= $client->config->merchant_id;
        $data['head']['tranDate']=date('Ymd',time());
        $data['head']['tranTime']=date('His',time());
        $data['head']['tranFlowid']= $client->config->merchant_id . date('YmdHis',time()) . substr(rand(),4);
        $data['head']['tranCode']= 'TD4005';
        $data['head']['respCode']='';
        $data['head']['respCesc']='';
        // 报文体
        $data['body']['custNo']= $custNo;//客户编号
        $data['body']['orderId']= $orderId; //自动生成
        $data['body']['bankCardNo']= $bankCardNo; //银行卡号
        $data['body']['idCardNo']= $idCardNo; //身份证
        $data['body']['mobile']= $mobile; //手机号
        $data['body']['name']=$name;//持卡人姓名
        $data['body']['tranAmt']=$totalAmt;//金额
        $data['body']['verifyCode']= $verifyCode;//验证码
        $res = $client->sendReq($data);
        return $res;
    }

    static function openCloudWeixin($token,$suiji_money,$notify_url)
    {

        $total_fee = round($suiji_money,2) * 100;
        $body = '微信支付';
        $out_trade_no = $token;
        $WeChat = new WxPay();
        $res = $WeChat->wechat_pay($body, $out_trade_no, $total_fee, $notify_url);
        return $res;
    }
    /**
     * 支付宝充值回调
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function alipay_recharge()
    {
        if(!request()->isPost()){
            self::writeLog(9,'回调失败：'.json_encode($_REQUEST));
            abort(404);
        }
        $out_trade_no = input('post.out_trade_no', null);
        $trade_status = input('post.trade_status', null);
        $total_amount = input('post.total_amount', null);
        $alipay = Db::name('config')->column('val','key');
        $aop = new AopClient;
        $aop->alipayrsaPublicKey = $alipay['alipayrsaPublicKey'];
        $signVerified = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        if($signVerified){
            if($trade_status!='TRADE_SUCCESS'){
                exit('failure');
            }
            // TODO 验签成功后
            //按照支付结果异步通知中的描述，对支付结果中的业务内容进行1\2\3\4二次校验，校验成功后在response中返回success，校验失败返回failure
            if ($out_trade_no){
                
                $ip = request()->ip();
                self::writeLog(88,'支付宝验签成功：'.'_ip:'.$ip.'_Post:'.json_encode($_POST).'_REQUEST:'.json_encode($_REQUEST),'支付宝验签成功');
                
//                $order_sn = explode("s",$out_trade_no)[1];
                $sign = substr($out_trade_no,0,2);
                if($sign=='ua'){
                    $order_sn = $out_trade_no;
                    $order_info = Db::name('prestore_recharge')->where('order_sn',$order_sn)->find();
                    if(!$order_info){
                        self::writeLog(6,'订单不存在：'.$order_sn);
                        exit('failure');
                    }
                    if($order_info['status']==1){
                        exit('success');
                    }
                    if($total_amount!=$order_info['amount']){
                        self::writeLog(7,'支付金额和订单金额不匹配：'.$order_sn);
                        exit('failure');
                    }
                    Db::name('prestore_recharge')->where('order_sn',$order_info['order_sn'])->update([
                        'status' => 1,
                        'pay_type' => 1,
                        'pay_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'integral' => Db::raw('integral+' . $order_info['bal_amount'])
                    ]);
                    Db::name('bal_record')->insert([
                        'number'=>$order_info['bal_amount'],
                        'type'=>6,
                        'info'=>'积分充值',
                        'member_id'=>$order_info['member_id']
                    ]);
                    Push::main()->administration_commission($order_info['member_id'],$order_info['amount']);
                    exit('success');
                }else{
                    $order_sn = $out_trade_no;
                    $order_info = Db::name('prestore_recharge')->where('order_sn',$order_sn)->find();
                    if(!$order_info){
                        self::writeLog(6,'订单不存在：'.$order_sn);
                        exit('failure');
                    }
                    if($order_info['status']==1){
                        exit('success');
                    }
                    if($total_amount!=$order_info['amount']){
                        self::writeLog(7,'支付金额和订单金额不匹配：'.$order_sn);
                        exit('failure');
                    }
                    Db::name('prestore_recharge')->where('order_sn',$order_info['order_sn'])->update([
                        'status' => 1,
                        'pay_type' => 1,
                        'pay_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'integral' => Db::raw('integral+' . $order_info['bal_amount'])
                    ]);
                    Db::name('bal_record')->insert([
                        'number'=>$order_info['bal_amount'],
                        'type'=>6,
                        'info'=>'积分充值',
                        'member_id'=>$order_info['member_id']
                    ]);
                    Push::main()->administration_commission($order_info['member_id'],$order_info['amount']);
                    exit('success');
                }

            }
        }else{
            //self::writeLog(8,'支付宝验签失败：'.json_encode($_POST));
            exit('failure');
        }
    }

    //条码支付积分充值回调
    public function jyt_recharge(){
        $data['merchant_id'] = $_POST['merchant_id'];
        $data['msg_enc'] = $_POST['msg_enc'];
        $data['key_enc'] = $_POST['key_enc'];
        $data['sign'] = $_POST['sign'];
        $data['mer_order_id'] = $_POST['mer_order_id'];
        $res = $this->accept_call_back($data);
        
        $ip = request()->ip();
        self::writeLog(8, 'JYT充值验签成功：ip:'.$ip.'_data:' . json_encode(($data)));
        
        if ($res) {
            $result = $res['body'];
            if($result['state'] == '11'){
                $order_sn = $result['merOrderId'];
                $order_info = Db::name('prestore_recharge')->where('order_sn',$order_sn)->find();
                if(!$order_info){
                    self::writeLog(6,'JYT充值订单不存在：'.$order_sn);
                    exit('failure');
                }
                if($order_info['status']==1){
                    exit('success');
                }
                $total_amount = $result['totalAmt'];
                if($total_amount!=$order_info['amount']){
                    self::writeLog(7,'JYT充值支付金额和订单金额不匹配：'.$order_sn);
                    exit('failure');
                }
                Db::name('prestore_recharge')->where('order_sn',$order_info['order_sn'])->update([
                    'status' => 1,
                    'pay_type' => 10,
                    'pay_time' => date('Y-m-d H:i:s')
                ]);
                Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                    'integral' => Db::raw('integral+' . $order_info['bal_amount'])
                ]);
                Db::name('bal_record')->insert([
                    'number'=>$order_info['bal_amount'],
                    'type'=>6,
                    'info'=>'积分充值',
                    'member_id'=>$order_info['member_id']
                ]);
                Push::main()->administration_commission($order_info['member_id'],$order_info['amount']);
                exit('success');
            }
        } else {
            $ip = request()->ip();
            self::writeLog(8, 'JYT充值验签失败：ip:'.$ip.'_data:' . json_encode(($data)));
        }
    }


    //jyt快捷支付回调
    public function jyt_card_pay_back(){
        $data['merchant_id'] = $_POST['merchant_id'];
        $data['msg_enc'] = $_POST['msg_enc'];
        $data['key_enc'] = $_POST['key_enc'];
        $data['sign'] = $_POST['sign'];
        $data['mer_order_id'] = $_POST['mer_order_id'];
        $res = $this->accept_call_card_back($data);
        //self::writeLog(6,json_encode($res),'卡支付回调');
        $ip = request()->ip();
        self::writeLog(6,json_encode($res),'卡支付回调 IP:'.$ip);
        if ($res) {
            $result = $res['body'];
            if($result['tranState'] == '00'){
                $order_sn = $result['orderId'];
                $a = mb_substr($order_sn,0,2);
                if($a == 'JF'){//积分充值
                    $order_info = Db::name('prestore_recharge')->where('order_sn',$order_sn)->find();
                    if(!$order_info){
                        self::writeLog(6,'JYT充值订单不存在：'.$order_sn);
                        exit('failure');
                    }
                    if($order_info['status']==1){
                        exit('success');
                    }
                    $total_amount = $result['tranAmt'];
                    if($total_amount!=$order_info['amount']){
                        self::writeLog(7,'JYT充值支付金额和订单金额不匹配：'.$order_sn);
                        exit('failure');
                    }
                    Db::name('prestore_recharge')->where('order_sn',$order_info['order_sn'])->update([
                        'status' => 1,
                        'pay_type' => 8,
                        'pay_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'integral' => Db::raw('integral+' . $order_info['bal_amount'])
                    ]);
                    Db::name('bal_record')->insert([
                        'number'=>$order_info['bal_amount'],
                        'type'=>6,
                        'info'=>'积分充值',
                        'member_id'=>$order_info['member_id']
                    ]);
                    Push::main()->administration_commission($order_info['member_id'],$order_info['amount']);
                    exit('success');
                }
                if($a == 'GW'){//商品购物
                    $total_amount = $result['tranAmt'];
                    $order_info = Db::name('mall_order')->where('order_sn',$order_sn)->find();
                    if(!$order_info){
                        self::writeLog(6,'jyt购物订单不存在：'.$order_sn.json_encode($data));
                        exit('failure');
                    }
                    if($order_info['order_status']==1 || $order_info['order_status']==3){
                        exit('success');
                    }
                    if($total_amount!=$order_info['order_amount']){
                        self::writeLog(7,'jyt购物支付金额和订单金额不匹配：'.$order_sn);
                        exit('failure');
                    }
                    Db::name('mall_order')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                        'order_status' => 1,
                        'amount'=>$total_amount,
                        'pay_time' => date('Y-m-d H:i:s'),
                        'pay_type' => 8,
                    ]);
                    if($order_info['goods_type'] == 5){//会员A区
                        Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$total_amount);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$total_amount);
                        //用户增加绿色积分
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                    }
                    
                    //积分区
                    if($order_info['goods_type'] == 6){
                        Push::main()->integral_rebate($total_amount,$order_info['member_id']);
                        Db::name('bal_record')->insert([
                            'number'=>round($total_amount*0.9,2),//消费积分赠送数量,
                            'type'=>6,
                            'info'=>'积分充值',
                            'member_id'=>$order_info['member_id']
                        ]);
                        Db::name('mall_order')->where(['order_main' => $order_sn])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                    }
                    
                    if($order_info['goods_type'] == 7){//会员B区
                        Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$total_amount);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$total_amount);
                        //用户增加绿色积分
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                        //返佣直推二代
                        Push::main()->vipB($order_info['member_id'],$total_amount);
                    }
                    exit('success');
                }
            }
        } else {
            self::writeLog(8, 'JYT充值验签失败：ip:'.$ip .'_data:' . json_encode(($data)));
        }
    }

    /**
     * 接受回调响应处理
     * @param $res_array
     */
    function accept_call_back($res_array)
    {
        $client = new JytJsonClient;
        $client->init();
        // 1. 解密验签
        $res_message = $client->parserRes($res_array);
        // 2. 响应报文明文解析（参考文档的判断）
        return json_decode($res_message,true);
    }

    /**
     * 接受回调响应处理
     * @param $res_array
     */
    function accept_call_card_back($res_array)
    {
        $client = new JytCarJsonClient();
        $client->init();
        // 1. 解密验签
        $res_message = $client->parserRes($res_array);
        // 2. 响应报文明文解析（参考文档的判断）
        return json_decode($res_message,true);
    }
    /**
     * 快捷支付验签
     * @return void
     */
    public function ef_recharge()
    {
        $post_data = file_get_contents("php://input");
        $sign = request()->header('x-efps-sign');
        $efalipay = new Efalipay();
        $res = $efalipay->rsaCheckV2($post_data, $sign);
        $ip = request()->ip();
        if ($res) {
            self::writeLog(8, 'ef充值验签成功：ip:'.$ip.'_data:' .$post_data );
            $result = json_decode($post_data, true);
            if($result['payState'] == 00){
                $order_sn = $result['outTradeNo'];
                $order_info = Db::name('prestore_recharge')->where('order_sn',$order_sn)->find();
                if(!$order_info){
                    self::writeLog(6,'ef充值订单不存在：'.$order_sn);
                    exit('failure');
                }
                if($order_info['status']==1){
                    exit('success');
                }
                $total_amount = $result['payerAmount']/100;
                if($total_amount!=$order_info['amount']){
                    self::writeLog(7,'ef充值支付金额和订单金额不匹配：'.$order_sn);
                    exit('failure');
                }
                Db::name('prestore_recharge')->where('order_sn',$order_info['order_sn'])->update([
                    'status' => 1,
                    'pay_type' => 8,
                    'pay_time' => date('Y-m-d H:i:s')
                ]);
                Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                    'integral' => Db::raw('integral+' . $order_info['bal_amount'])
                ]);
                Db::name('bal_record')->insert([
                    'number'=>$order_info['bal_amount'],
                    'type'=>6,
                    'info'=>'积分充值',
                    'member_id'=>$order_info['member_id']
                ]);
                Push::main()->administration_commission($order_info['member_id'],$order_info['amount']);
                exit('success');
            }
        } else {
            self::writeLog(8, 'ef充值验签失败：ip:'.$ip.'_data:' . $post_data);
        }
    }

    /**
     * @return void 快捷支付支购物回调
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function ef_goods_return()
    {
        $post_data = file_get_contents("php://input");
        $sign = request()->header('x-efps-sign');
        $efalipay = new Efalipay();
        $res = $efalipay->rsaCheckV2($post_data, $sign);
        $ip = request()->ip();
        if($res){
            self::writeLog(8, 'ef充值验签：ip:'.$ip.'_data:' .$post_data );
            $result = json_decode($post_data, true);
            if($result['payState'] == 00){
                $order_sn = $result['outTradeNo'];

                $total_amount = $result['payerAmount']/100;
                $order_info = Db::name('mall_order')->where('order_sn',$order_sn)->find();
                if(!$order_info){
                    self::writeLog(6,'ef购物订单不存在：'.$order_sn.$post_data);
                    exit('failure');
                }
                if($order_info['order_status']==1 || $order_info['order_status']==3){
                    exit('success');
                }
                if($total_amount!=$order_info['order_amount']){
                    self::writeLog(7,'ef购物支付金额和订单金额不匹配：'.$order_sn);
                    exit('failure');
                }
                Db::name('mall_order')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                    'order_status' => 1,
                    'amount'=>$total_amount,
                    'pay_time' => date('Y-m-d H:i:s'),
                    'pay_type' => 8,
                ]);
                if($order_info['goods_type'] == 5){
                    Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'is_vip'=>1
                    ]);
                    //给城市代理和驿站代理反佣
                    Push::main()->city_commission($order_info['member_id'],$total_amount);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$total_amount);
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                }
                exit('success');
            }
        }else{
            self::writeLog(8, 'ef购物验签失败：ip:'.$ip .'_data:'. $post_data);
        }
    }
    /**
     * @return void 条码支付支购物回调
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function jyt_goods_return()
    {
        $data['merchant_id'] = $_POST['merchant_id'];
        $data['msg_enc'] = $_POST['msg_enc'];
        $data['key_enc'] = $_POST['key_enc'];
        $data['sign'] = $_POST['sign'];
        $data['mer_order_id'] = $_POST['mer_order_id'];
        $ip = request()->ip();
        self::writeLog(6,json_encode($data),'jyt回调日志 ip:'.$ip);
        $res = $this->accept_call_back($data);
        if ($res) {
            $result = $res['body'];
            if($result['state'] == '11'){
                $order_sn = $result['merOrderId'];

                $total_amount = $result['totalAmt'];
                $order_info = Db::name('mall_order')->where('order_sn',$order_sn)->find();
                if(!$order_info){
                    self::writeLog(6,'jyt购物订单不存在：'.$order_sn.json_encode($data));
                    exit('failure');
                }
                if($order_info['order_status']==1 || $order_info['order_status']==3){
                    exit('success');
                }
                if($total_amount!=$order_info['order_amount']){
                    self::writeLog(7,'jyt购物支付金额和订单金额不匹配：'.$order_sn);
                    exit('failure');
                }
                Db::name('mall_order')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                    'order_status' => 1,
                    'amount'=>$total_amount,
                    'pay_time' => date('Y-m-d H:i:s'),
                    'pay_type' => 10,
                ]);
                if($order_info['goods_type'] == 5){
                    Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'is_vip'=>1
                    ]);
                    //给城市代理和驿站代理反佣
                    Push::main()->city_commission($order_info['member_id'],$total_amount);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$total_amount);
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                }
                //积分区
                if($order_info['goods_type'] == 6){
                    Push::main()->integral_rebate($total_amount,$order_info['member_id']);
                    Db::name('bal_record')->insert([
                        'number'=>round($total_amount*0.9,2),//消费积分赠送数量,
                        'type'=>6,
                        'info'=>'积分充值',
                        'member_id'=>$order_info['member_id']
                    ]);
                    Db::name('mall_order')->where(['order_main' => $order_sn])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                }
                if($order_info['goods_type'] == 7){//会员B区
                    Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'is_vip'=>1
                    ]);
                    //给城市代理和驿站代理反佣
                    Push::main()->city_commission($order_info['member_id'],$total_amount);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$total_amount);
                    //用户增加绿色积分
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                    //返佣直推二代
                     Push::main()->vipB($order_info['member_id'],$total_amount);
                }
                exit('success');
            }
        }else{
            self::writeLog(8, 'jyt购物验签失败：ip:'.$ip.'_data:' . json_encode($data));
        }
    }
    /**
     * @return void 条码支付购物回调批量
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function jyt_goods_return_all()
    {
        $data['merchant_id'] = $_POST['merchant_id'];
        $data['msg_enc'] = $_POST['msg_enc'];
        $data['key_enc'] = $_POST['key_enc'];
        $data['sign'] = $_POST['sign'];
        $data['mer_order_id'] = $_POST['mer_order_id'];
        $ip = request()->ip();
        self::writeLog(6,json_encode($data),'jyt回调日志2 ip:'.$ip);
        $res = $this->accept_call_back($data);
        if ($res) {
            $result = $res['body'];
            if($result['state'] == '11'){
                $order_sn = $result['merOrderId'];

                $total_amount = $result['totalAmt'];
                $order_info = Db::name('mall_order_all')->where('order_sn',$order_sn)->find();
                if(!$order_info){
                    self::writeLog(61,'订单不存在：'.$order_sn.json_encode($data));
                    exit('failure');
                }
                if($order_info['order_status']==1 || $order_info['order_status']==3){
                    exit('success');
                }
                if($total_amount!=$order_info['order_amount']){
                    self::writeLog(71,'jyt支付金额和订单金额不匹配：'.$order_sn);
                    exit('failure');
                }
                Db::name('mall_order_all')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                    'order_status' => 1,
                    'amount'=>$total_amount,
                    'pay_time' => date('Y-m-d H:i:s'),
                    'pay_type' => 10,
                ]);
                $order = Db::name('mall_order')->field('id,order_amount')->where(['order_status' => 0, 'order_main' => $order_sn])->select();
                foreach ($order as $value){
                    Db::name('mall_order')->where(['id' => $value['id']])->update([
                        'order_status' => 1,
                        'amount' => $value['order_amount'],
                        'pay_time' => date('Y-m-d H:i:s'),
                        'pay_type' => 10,
                    ]);

                }
                //增加销量
                $order_product = Db::name('mall_order')->where(['order_main' => $order_sn])->select();
                foreach ($order_product as $v) {
                    Db::name('mall_product_spec')->where(['spec_id' => $v['spec_id']])->setInc('sales', $v['num']);
                    Db::name('mall_product')->where(['id' => $v['goods_id']])->setInc('sales', $v['num']);
                }
                if($order_info['goods_type'] == 5){
                    Db::name('mall_order')->where(['order_main' => $order_sn])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'is_vip'=>1
                    ]);
                    //给城市代理和驿站代理反佣
                    Push::main()->city_commission($order_info['member_id'],$total_amount);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$total_amount);
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                }
                //积分区
                if($order_info['goods_type'] == 6){
                    Db::name('bal_record')->insert([
                        'number'=>round($total_amount*0.9,2),//消费积分赠送数量,
                        'type'=>6,
                        'info'=>'积分充值',
                        'member_id'=>$order_info['member_id']
                    ]);
                    Push::main()->integral_rebate($total_amount,$order_info['member_id']);
                    Db::name('mall_order')->where(['order_main' => $order_sn])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                }
                if($order_info['goods_type'] == 7){//会员B区
                    Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'is_vip'=>1
                    ]);
                    //给城市代理和驿站代理反佣
                    Push::main()->city_commission($order_info['member_id'],$total_amount);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$total_amount);
                    //用户增加绿色积分
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                    //返佣直推二代
                    Push::main()->vipB($order_info['member_id'],$total_amount);
                }
                exit('success');
            }
        }else{
            self::writeLog(81,'jyt购物验签失败：ip:'.$ip .'_data:'.json_encode($data));
        }
    }
    /**
     * @return void 快捷支付购物回调批量
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function ef_goods_return_all()
    {
        $post_data = file_get_contents("php://input");
        $sign = request()->header('x-efps-sign');
        $efalipay = new Efalipay();
        $res = $efalipay->rsaCheckV2($post_data, $sign);
        $ip = request()->ip();
        if($res){
            self::writeLog(81,'ef购物验签成功：ip:'.$ip.'_data:'.json_encode($post_data));
            
            $result = json_decode($post_data, true);
            if($result['payState'] == 00){
                //后面的逻辑代码放这里
                $order_sn = $result['outTradeNo'];
                $total_amount = $result['payerAmount']/100;
                $order_info = Db::name('mall_order_all')->where('order_sn',$order_sn)->find();
                if(!$order_info){
                    self::writeLog(61,'订单不存在：'.$order_sn.$post_data);
                    exit('failure');
                }
                if($order_info['order_status']==1 || $order_info['order_status']==3){
                    exit('success');
                }
                if($total_amount!=$order_info['order_amount']){
                    self::writeLog(71,'支付金额和订单金额不匹配：'.$order_sn);
                    exit('failure');
                }
                Db::name('mall_order_all')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                    'order_status' => 1,
                    'amount'=>$total_amount,
                    'pay_time' => date('Y-m-d H:i:s'),
                    'pay_type' => 8,
                ]);
                $order = Db::name('mall_order')->field('id,order_amount')->where(['order_status' => 0, 'order_main' => $order_sn])->select();
                foreach ($order as $value){
                    Db::name('mall_order')->where(['id' => $value['id']])->update([
                        'order_status' => 1,
                        'amount' => $value['order_amount'],
                        'pay_time' => date('Y-m-d H:i:s'),
                        'pay_type' => 8,
                    ]);

                }
                //增加销量
                $order_product = Db::name('mall_order')->where(['order_main' => $order_sn])->select();
                foreach ($order_product as $v) {
                    Db::name('mall_product_spec')->where(['spec_id' => $v['spec_id']])->setInc('sales', $v['num']);
                    Db::name('mall_product')->where(['id' => $v['goods_id']])->setInc('sales', $v['num']);
                }
                if($order_info['goods_type'] == 5){
                    Db::name('mall_order')->where(['order_main' => $order_sn])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'is_vip'=>1
                    ]);
                    //给城市代理和驿站代理反佣
                    Push::main()->city_commission($order_info['member_id'],$total_amount);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$total_amount);
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                }
                if($order_info['goods_type'] == 7){//会员B区
                    Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'is_vip'=>1
                    ]);
                    //给城市代理和驿站代理反佣
                    Push::main()->city_commission($order_info['member_id'],$total_amount);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$total_amount);
                    //用户增加绿色积分
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                    //返佣直推二代
                    Push::main()->vipB($order_info['member_id'],$total_amount);
                }
                exit('success');
            }
        }else{
            self::writeLog(81,'ef购物验签失败：ip:'.$ip.'_data:'.json_encode($post_data));
        }
    }
    /**
     * @return void 微信支付充值回调
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function wx_recharge()
    {
        $testxml = file_get_contents("php://input");
        $jsonxml = json_encode(simplexml_load_string($testxml, 'SimpleXMLElement', LIBXML_NOCDATA));
        $result = json_decode($jsonxml, true);//转成数组，
        //验证回调
        $wx = new WxPay();
        //为了防止假数据，验证签名是否和返回的一样。
        //记录一下，返回回来的签名，生成签名的时候，必须剔除sign字段。
        $sign = $result['sign'];
        unset($result['sign']);
        
        $ip = request()->ip();
        
        if ($sign == $wx->getSign($result)) {
            
            self::writeLog(8,'wx验签成功：ip:'.$ip.'_data:'.json_encode($result));
            
            //签名验证成功后，判断返回微信返回的
            if ($result['result_code'] == 'SUCCESS' && $result['return_code'] == "SUCCESS") {
                //后面的逻辑代码放这里
                $order_sn = $result['out_trade_no'];
                $order_info = Db::name('prestore_recharge')->where('order_sn',$order_sn)->find();
                if(!$order_info){
                    self::writeLog(6,'订单不存在：'.$order_sn);
                    exit('failure');
                }
                if($order_info['status']==1){
                    exit('success');
                }
                $total_amount = $result['total_fee']/100;
                if($total_amount!=$order_info['amount']){
                    self::writeLog(7,'支付金额和订单金额不匹配：'.$order_sn);
                    exit('failure');
                }
                Db::name('prestore_recharge')->where('order_sn',$order_info['order_sn'])->update([
                    'status' => 1,
                    'pay_type' => 2,
                    'pay_time' => date('Y-m-d H:i:s')
                ]);
                Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                    'integral' => Db::raw('integral+' . $order_info['bal_amount'])
                ]);
                Db::name('bal_record')->insert([
                    'number'=>$order_info['bal_amount'],
                    'type'=>6,
                    'info'=>'积分充值',
                    'member_id'=>$order_info['member_id']
                ]);
                Push::main()->administration_commission($order_info['member_id'],$order_info['amount']);
                exit('success');
            }
        } else {
            self::writeLog(8,'wx验签失败：ip:'.$ip.'_data:'.json_encode($result));
        }
    }
    /**
     * @return void 微信支购物回调
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function wx_goods_return()
    {
        $testxml = file_get_contents("php://input");
        $jsonxml = json_encode(simplexml_load_string($testxml, 'SimpleXMLElement', LIBXML_NOCDATA));
        $result = json_decode($jsonxml, true);//转成数组，
        //验证回调
        $wx = new WxPay();
        //为了防止假数据，验证签名是否和返回的一样。
        //记录一下，返回回来的签名，生成签名的时候，必须剔除sign字段。
        $sign = $result['sign'];
        unset($result['sign']);
        
        $ip = request()->ip();
        
        if ($sign == $wx->getSign($result)) {
            
            self::writeLog(8,'wx验签成功：ip:'.$ip.'_data:'.json_encode($result));
            
            //签名验证成功后，判断返回微信返回的
            if ($result['result_code'] == 'SUCCESS' && $result['return_code'] == "SUCCESS") {
                //后面的逻辑代码放这里
                $order_sn = $result['out_trade_no'];

               $total_amount = $result['total_fee']/100;
                $order_info = Db::name('mall_order')->where('order_sn',$order_sn)->find();
                if(!$order_info){
                    self::writeLog(6,'订单不存在：'.$order_sn);
                    exit('failure');
                }
                if($order_info['order_status']==1 || $order_info['order_status']==3){
                    exit('success');
                }
                if($total_amount!=$order_info['order_amount']){
                    self::writeLog(7,'支付金额和订单金额不匹配：'.$order_sn);
                    exit('failure');
                }
                Db::name('mall_order')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                    'order_status' => 1,
                    'amount'=>$total_amount,
                    'pay_time' => date('Y-m-d H:i:s'),
                    'pay_type' => 4,
                ]);
                if($order_info['goods_type'] == 5){
                    Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'is_vip'=>1
                    ]);
                    //给城市代理和驿站代理反佣
                    Push::main()->city_commission($order_info['member_id'],$total_amount);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$total_amount);
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                }
                if($order_info['goods_type'] == 7){//会员B区
                    Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'is_vip'=>1
                    ]);
                    //给城市代理和驿站代理反佣
                    Push::main()->city_commission($order_info['member_id'],$total_amount);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$total_amount);
                    //用户增加绿色积分
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                    //返佣直推二代
                    Push::main()->vipB($order_info['member_id'],$total_amount);
                }
                exit('success');
            }
        } else {
            self::writeLog(8,'wx验签失败：ip:'.$ip.'_data:'.json_encode($result));
        }
    }

    /**
     * @return void 微信支购物回调批量
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function wx_goods_return_all()
    {
        $testxml = file_get_contents("php://input");
        $jsonxml = json_encode(simplexml_load_string($testxml, 'SimpleXMLElement', LIBXML_NOCDATA));
        $result = json_decode($jsonxml, true);//转成数组，
        //验证回调
        $wx = new WxPay();
        //为了防止假数据，验证签名是否和返回的一样。
        //记录一下，返回回来的签名，生成签名的时候，必须剔除sign字段。
        $sign = $result['sign'];
        unset($result['sign']);
        
        $ip = request()->ip();
        
        if ($sign == $wx->getSign($result)) {
            
            self::writeLog(81,'wx验签成功：ip:'.$ip.'_data:'.json_encode($result));
            //签名验证成功后，判断返回微信返回的
            if ($result['result_code'] == 'SUCCESS' && $result['return_code'] == "SUCCESS") {
                //后面的逻辑代码放这里
                $order_sn = $result['out_trade_no'];

                $total_amount = $result['total_fee']/100;
                $order_info = Db::name('mall_order_all')->where('order_sn',$order_sn)->find();
                if(!$order_info){
                    self::writeLog(61,'订单不存在：'.$order_sn);
                    exit('failure');
                }
                if($order_info['order_status']==1 || $order_info['order_status']==3){
                    exit('success');
                }
                if($total_amount!=$order_info['order_amount']){
                    self::writeLog(71,'支付金额和订单金额不匹配：'.$order_sn);
                    exit('failure');
                }
                Db::name('mall_order_all')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                    'order_status' => 1,
                    'amount'=>$total_amount,
                    'pay_time' => date('Y-m-d H:i:s'),
                    'pay_type' => 4,
                ]);
                $order = Db::name('mall_order')->field('order_id,order_amount')->where(['order_status' => 0, 'order_main' => $order_sn])->select();
                foreach ($order as $value){
                    Db::name('mall_order')->where(['order_id' => $value['order_id']])->update([
                        'order_status' => 1,
                        'amount' => $value['order_amount'],
                        'pay_time' => date('Y-m-d H:i:s'),
                        'pay_type' => 4,
                    ]);

                }
                //增加销量
                $order_product = Db::name('mall_order')->where(['order_main' => $order_sn])->select();
                foreach ($order_product as $v) {
                    Db::name('mall_product_spec')->where(['spec_id' => $v['spec_id']])->setInc('sales', $v['num']);
                    Db::name('mall_product')->where(['id' => $v['goods_id']])->setInc('sales', $v['num']);
                }
                if($order_info['goods_type'] == 5){
                    Db::name('mall_order')->where(['order_main' => $order_sn])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'is_vip'=>1
                    ]);
                    //给城市代理和驿站代理反佣
                    Push::main()->city_commission($order_info['member_id'],$total_amount);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$total_amount);
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                }
                if($order_info['goods_type'] == 7){//会员B区
                    Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'is_vip'=>1
                    ]);
                    //给城市代理和驿站代理反佣
                    Push::main()->city_commission($order_info['member_id'],$total_amount);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$total_amount);
                    //用户增加绿色积分
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                    //返佣直推二代
                    Push::main()->vipB($order_info['member_id'],$total_amount);
                }
                exit('success');
            }
        } else {
            self::writeLog(81,'wx验签失败：ip:'.$ip.'_data:'.json_encode($result));
        }
    }


    /**
     * @return void 支付宝购物回调
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function alipay_goods_return()
    {
        if(!request()->isPost()){
            self::writeLog(9,'回调失败：'.json_encode($_REQUEST));
            abort(404);
        }
        $out_trade_no = input('post.out_trade_no', null);
        $trade_status = input('post.trade_status', null);
        $total_amount = input('post.total_amount', null);
        $aop = new AopClient;
        $alipay = Db::name('config')->column('val','key');
        $aop->alipayrsaPublicKey = $alipay['alipayrsaPublicKey'];
        $signVerified = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        if($signVerified){
            if($trade_status!='TRADE_SUCCESS'){
                exit('failure');
            }
            // TODO 验签成功后
            //按照支付结果异步通知中的描述，对支付结果中的业务内容进行1\2\3\4二次校验，校验成功后在response中返回success，校验失败返回failure
            if ($out_trade_no){
                
                $ip = request()->ip();
                self::writeLog(88,'支付宝验签成功：'.'_IP:'.$ip.'_Post:'.json_encode($_POST).'_REQUEST:'.json_encode($_REQUEST),'支付宝验签成功');
                
//                $order_sn = explode("s",$out_trade_no)[1];
                $sign = substr($out_trade_no,0,2);
                if($sign=='ua'){
                    $order_sn = $out_trade_no;
                    $order_info = Db::name('mall_order')->where('order_sn',$order_sn)->find();
                    if(!$order_info){
                        self::writeLog(6,'订单不存在：'.$order_sn);
                        exit('failure');
                    }
                    if($order_info['order_status']==1 || $order_info['order_status']==3){
                        exit('success');
                    }
                    if($total_amount!=$order_info['order_amount']){
                        self::writeLog(7,'支付金额和订单金额不匹配：'.$order_sn);
                        exit('failure');
                    }
                    Db::name('mall_order')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                        'order_status' => 1,
                        'amount'=>$total_amount,
                        'pay_time' => date('Y-m-d H:i:s'),
                        'pay_type' => 3
                    ]);
                    if($order_info['goods_type'] == 5){
                        Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$total_amount);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$total_amount);
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                    }
                    //积分区
                    if($order_info['goods_type'] == 6){
                        Db::name('bal_record')->insert([
                            'number'=>round($total_amount*0.9,2),//消费积分赠送数量,
                            'type'=>6,
                            'info'=>'积分充值',
                            'member_id'=>$order_info['member_id']
                        ]);
                        Push::main()->integral_rebate($total_amount,$order_info['member_id']);
                        Db::name('mall_order')->where(['order_main' => $order_sn])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                    }
                    if($order_info['goods_type'] == 7){//会员B区
                        Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$total_amount);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$total_amount);
                        //用户增加绿色积分
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                        //返佣直推二代
                        Push::main()->vipB($order_info['member_id'],$total_amount);
                    }
                    exit('success');
                }else{
                    $order_sn = $out_trade_no;
                    $order_info = Db::name('mall_order')->where('order_sn',$order_sn)->find();
                    if(!$order_info){
                        self::writeLog(6,'订单不存在：'.$order_sn);
                        exit('failure');
                    }
                    if($order_info['order_status']==1 || $order_info['order_status']==3){
                        exit('success');
                    }
                    if($total_amount!=$order_info['order_amount']){
                        self::writeLog(7,'支付金额和订单金额不匹配：'.$order_sn);
                        exit('failure');
                    }
                    Db::name('mall_order')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                        'order_status' => 1,
                        'amount'=>$total_amount,
                        'pay_time' => date('Y-m-d H:i:s'),
                        'pay_type' => 3,
                    ]);
                    if($order_info['goods_type'] == 5){
                        Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$total_amount);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$total_amount);
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                    }
                    //积分区
                    if($order_info['goods_type'] == 6){
                        Db::name('bal_record')->insert([
                            'number'=>round($total_amount*0.9,2),//消费积分赠送数量,
                            'type'=>6,
                            'info'=>'积分充值',
                            'member_id'=>$order_info['member_id']
                        ]);
                        Push::main()->integral_rebate($total_amount,$order_info['member_id']);
                        Db::name('mall_order')->where(['order_main' => $order_sn])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                    }
                    if($order_info['goods_type'] == 7){//会员B区
                        Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$total_amount);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$total_amount);
                        //用户增加绿色积分
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                        //返佣直推二代
                        Push::main()->vipB($order_info['member_id'],$total_amount);
                    }
                    exit('success');
                }

            }
        }else{
            $ip = request()->ip();
            self::writeLog(82,'支付宝验签失败：'.'_IP:'.$ip.'_Post:'.json_encode($_POST).'_REQUEST:'.json_encode($_REQUEST));
            exit('failure');
        }
    }

    /**
     * @return void 支付宝购物回调批量
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function alipay_goods_return_all()
    {
        if(!request()->isPost()){
            self::writeLog(9,'回调失败：'.json_encode($_REQUEST));
            abort(404);
        }
        $out_trade_no = input('post.out_trade_no', null);
        $trade_status = input('post.trade_status', null);
        $total_amount = input('post.total_amount', null);
        $aop = new AopClient;
        $alipay = Db::name('config')->column('val','key');
        $aop->alipayrsaPublicKey = $alipay['alipayrsaPublicKey'];
        $signVerified = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        if($signVerified){
            if($trade_status!='TRADE_SUCCESS'){
                exit('failure');
            }
            // TODO 验签成功后
            //按照支付结果异步通知中的描述，对支付结果中的业务内容进行1\2\3\4二次校验，校验成功后在response中返回success，校验失败返回failure
            if ($out_trade_no){
                
                $ip = request()->ip();
                self::writeLog(88,'支付宝验签成功：'.'_IP:'.$ip.'_POST:'.json_encode($_POST).'_REQUEST:'.json_encode($_REQUEST),'支付宝验签成功');
                
//              $order_sn = explode("s",$out_trade_no)[1];
                $sign = substr($out_trade_no,0,2);
                if($sign=='ua'){
                    $order_sn = $out_trade_no;
                    $order_info = Db::name('mall_order_all')->where('order_sn',$order_sn)->find();
                    if(!$order_info){
                        self::writeLog(61,'订单不存在：'.$order_sn);
                        exit('failure');
                    }
                    if($order_info['order_status']==1 || $order_info['order_status']==3){
                        exit('success');
                    }
                    if($total_amount!=$order_info['order_amount']){
                        self::writeLog(71,'支付金额和订单金额不匹配：'.$order_sn);
                        exit('failure');
                    }
                    Db::name('mall_order_all')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                        'order_status' => 1,
                        'amount'=>$total_amount,
                        'pay_time' => date('Y-m-d H:i:s'),
                        'pay_type' => 4,
                    ]);
                    $order = Db::name('mall_order')->field('id,order_amount')->where(['order_status' => 0, 'order_main' => $order_sn])->select();
                    foreach ($order as $value){
                        Db::name('mall_order')->where(['id' => $value['id']])->update([
                            'order_status' => 1,
                            'amount' => $value['order_amount'],
                            'pay_time' => date('Y-m-d H:i:s'),
                            'pay_type' => 4,
                        ]);

                    }
                    //增加销量
                    $order_product = Db::name('mall_order')->where(['order_main' => $order_sn])->select();
                    foreach ($order_product as $v) {
                        Db::name('mall_product_spec')->where(['spec_id' => $v['spec_id']])->setInc('sales', $v['num']);
                        Db::name('mall_product')->where(['id' => $v['goods_id']])->setInc('sales', $v['num']);
                    }
                    if($order_info['goods_type'] == 5){
                        Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$total_amount);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$total_amount);
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                    }
                    //积分区
                    if($order_info['goods_type'] == 6){
                        Db::name('bal_record')->insert([
                            'number'=>round($total_amount*0.9,2),//消费积分赠送数量,
                            'type'=>6,
                            'info'=>'积分充值',
                            'member_id'=>$order_info['member_id']
                        ]);
                        Push::main()->integral_rebate($total_amount,$order_info['member_id']);
                        Db::name('mall_order')->where(['order_main' => $order_sn])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                    }
                    if($order_info['goods_type'] == 7){//会员B区
                        Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$total_amount);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$total_amount);
                        //用户增加绿色积分
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                        //返佣直推二代
                        Push::main()->vipB($order_info['member_id'],$total_amount);
                    }
                    exit('success');
                }else{
                    $order_sn = $out_trade_no;
                    $order_info = Db::name('mall_order_all')->where('order_sn',$order_sn)->find();
                    if(!$order_info){
                        self::writeLog(61,'订单不存在：'.$order_sn);
                        exit('failure');
                    }
                    if($order_info['order_status']==1 || $order_info['order_status']==3){
                        exit('success');
                    }
                    if($total_amount!=$order_info['order_amount']){
                        self::writeLog(7,'支付金额和订单金额不匹配：'.$order_sn);
                        exit('failure');
                    }
                    Db::name('mall_order_all')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                        'order_status' => 1,
                        'amount'=>$total_amount,
                        'pay_time' => date('Y-m-d H:i:s'),
                        'pay_type' => 4,
                    ]);
                    Db::name('mall_order')->where(['order_status' => 0, 'order_main' => $order_sn])->update([
                        'order_status' => 1,
                        'amount'=>$total_amount,
                        'pay_time' => date('Y-m-d H:i:s'),
                        'pay_type' => 4,
                    ]);
                    if($order_info['goods_type'] == 5){
                        Db::name('mall_order')->where(['order_main' => $order_sn])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$total_amount);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$total_amount);
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                    }
                    //积分区
                    if($order_info['goods_type'] == 6){
                        Db::name('bal_record')->insert([
                            'number'=>round($total_amount*0.9,2),//消费积分赠送数量,
                            'type'=>6,
                            'info'=>'积分充值',
                            'member_id'=>$order_info['member_id']
                        ]);
                        Push::main()->integral_rebate($total_amount,$order_info['member_id']);
                        Db::name('mall_order')->where(['order_main' => $order_sn])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                    }
                    if($order_info['goods_type'] == 7){//会员B区
                        Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$total_amount);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$total_amount);
                        //用户增加绿色积分
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                        //返佣直推二代
                        Push::main()->vipB($order_info['member_id'],$total_amount);
                    }
                    exit('success');
                }

            }
        }else{
            $ip = request()->ip();
            self::writeLog(82,'支付宝验签失败：'.'_IP:'.$ip.'_POST:'.json_encode($_POST).'_REQUEST:'.json_encode($_REQUEST));
            exit('failure');
        }
    }
    /**
     * 云众包3.0付款 回调
     * @return void
     */
    public function fastIssuingReturn(){
        $data = input('post.');
        $yunzhong = new YunZhong();
        $result = $yunzhong->rsaCheck($data);
        if($result){
            if($data['return_code']!='SUCCESS'){
                exit('FAIL');
            }
            $aes = new AES();
            $orderInfo = $aes::main('f7b11d1bc124b5f9')->decrypt($data['resoult']);//解密
            $orderInfo = json_decode(base64_decode($orderInfo),true);
            if($data['type'] == 'create_batch_order'){
                $order_sn = $orderInfo['trade_number'];
                $order = Db::name('commission_withdraw')->where(['order_sn'=>$order_sn])->find();
                if(!$order){
                    self::writeLog(51,'云众包3.0订单不存在：'.$order_sn);
                    exit('FAIL');
                }
                if($order['status']==2){
                    exit('SUCCESS');
                }
                $enterprise_id = $orderInfo['data'][0]['enterprise_order_id'];
                Db::name('commission_withdraw')->where(['order_sn'=>$order_sn])->update([
                    'enterprise_order_id'=>$enterprise_id,
                    'status'=>2
                ]);
                $rechinge = $yunzhong->changeOrder($enterprise_id);
                if ($rechinge['code'] == 200){
                    Db::name('commission_withdraw')->where(['order_sn'=>$order_sn])->update([
                        'status'=>4
                    ]);
                }else{
                    self::writeLog(52,'批次审核失败！：'.json_encode($rechinge));

                }
                exit('SUCCESS');
            }else{
                $order_sn = $orderInfo['request_no'];
                $order = Db::name('commission_withdraw')->where(['order_sn'=>$order_sn])->find();
                if(!$order){
                    self::writeLog(53,'批次审核订单不存在！：'.$order_sn);
                    exit('FAIL');
                }
                if($order['status']==3){
                    exit('SUCCESS');
                }
                Db::name('commission_withdraw')->where(['order_sn'=>$order_sn])->update([
                    'status'=>3
                ]);
                exit('SUCCESS');
            }


        }else{
            self::writeLog(50,'云众包3.0验签失败！：'.json_encode($data));
        }


    }
    static private function writeLog($type,$msg,$err_level='错误'){
        Db::name('log')->insert([
            'level' => $err_level,
            'type' => $type,
            'msg' => $msg,
        ]);
    }

    //云银支付配置
    /*OLD
    static function YyConfig(){
        return [
            'mchId'     => 6714,//商户号
            'key'       => '6SiFzglP0F1Lhv1FyP1FUwM3HOaPd6X3',//应用密钥
            'signType'  => 'MD5',
            'reserve'   => '625841',//预留信息
            'apiUrl'    => 'https://api.yiyinpay.com:9500/ThirdApi/Pay/create_order',//远程下单地址
            'notify_url'=> 'https://shop.gxqhydf520.com/index/payment/Yy_notify'//回调地址
        ];
    }
    */
    static function YyConfig(){
        //旧版
//        return [
//            'mchId'     => 7570,//商户号
//            'key'       => 'w6iZMk8JSrmV9V3u8NP5Vz8zSy4gfm2E',//应用密钥
//            'signType'  => 'MD5',
//            'reserve'   => '3484',//预留信息
//            'apiUrl'    => 'https://api.yiyinpay.com:9500/ThirdApi/Pay/create_order',//远程下单地址
//            'notify_url'=> 'https://shop.gxqhydf520.com/index/payment/Yy_notify'//回调地址
//        ];
        //新版
        return [
            'mchNo'     => 'Y1720507697',//商户号
            'apiInfo'   => '3489',//预留信息
            'apiUrl'    => 'https://payment.yiyunhuipay.com/api/pay/infoPayOrderApi',//远程下单地址
            'notify_url'=> 'https://shop.gxqhydf520.com/index/payment/Yy_notify'//回调地址
        ];
    }

    //杉德支付配置 快捷支付
    static function shandePayConfig(){
        return [
            'mer_no'            => '6888804121482',//商户号
            'notify_url'        => 'https://shop.gxqhydf520.com/index/payment/shande_notify',//订单支付异步通知
            'return_url'        => 'https://shop.gxqhydf520.com/index/index/pay_success',//订单前端页面跳转地址
            'store_id'          => '000000',//门店号没有就填默认值 000000
            'product_code'      => '05030001',//产品编码 05030001:银联-一键快捷
            'privateKeyPath'    => 'certs/fengzhilin.pfx',//证书路径
            'privateKeyPwd'     => '568392',//证书密码
        ];
    }
    //杉德支付配置 聚合码
    static function shandePayConfig2(){
        return [
            'mer_no'            => '6888804121482',//商户号
            'notify_url'        => 'https://shop.gxqhydf520.com/index/payment/shande_notify',//订单支付异步通知
            'return_url'        => 'https://shop.gxqhydf520.com/index/index/pay_success',//订单前端页面跳转地址
            'store_id'          => '000000',//门店号没有就填默认值 000000
            'product_code'      => '02000001',//产品编码 02000001:银联-聚合码
            'privateKeyPath'    => 'certs/fengzhilin.pfx',//证书路径
            'privateKeyPwd'     => '568392',//证书密码
        ];
    }

    static function shandeH5PayConfig(){
        return [
            'notify_url'        => 'https://shop.gxqhydf520.com/index/payment/shande_notify',//订单支付异步通知
            'return_url'        => 'https://shop.gxqhydf520.com/index/index/pay_success',//订单前端页面跳转地址
        ];
    }

    static function shengPayConfig(){
        return [
            'mchId'        => '42673174',//商户号
            'notify_url'   => 'https://shop.gxqhydf520.com/index/payment/shengpay_notify',//订单支付异步通知
        ];
    }

    //盛付通-微信
    static function shengPay($money,$sn,$subject,$body,$ip)
    {
        require_once  dirname(__FILE__ ).'/'."/../../lib/shengpay/lib/ShengPay.Client.php";
        require_once  dirname(__FILE__ ).'/'."/../../lib/shengpay/example/ShengPay.Config.php";
        $config = new ShengPayConfig();
        $request = new \PreUnifieAppletdOrderRequest();
        $configs = self::shengPayConfig();
        $notify_url = $configs['notify_url'];
        $mchId = $configs['mchId'];//商户号
        $request->setTradeType('wx_lite');
        $request->setBody($body);
        $request->setCurrency('CNY');
        $request->setNotifyUrl($notify_url);
        $request->setOutTradeNo($sn);
        $request->setTimeExpire(date('YmdHis')+900);
        $request->setTotalFee($money*100);
        $request->setClientIp($ip);
        $request->setMchId($mchId);
        $result = \ShengPayClient::execute($request, $config);
        $res = json_decode($result,true);
        if($res['resultCode'] == 'SUCCESS'){
            $payInfo = json_decode($res['payInfo'],true);
            $pay_url = str_replace("?appletToken", "&query", $payInfo['appletUrl']);;
            $url = 'weixin://dl/business?appid='. $payInfo['useAppid'] .'&path=' . $pay_url;
            return ['code'=>200,'msg'=>'','data'=>$url];
        }else{
            return ['code'=>201,'msg'=>'支付拉取失败','data'=>$res];
        }
    }
    //盛付通-支付宝
    static function shengPay2($money,$sn,$subject,$body,$ip)
    {
        require_once  dirname(__FILE__ ).'/'."/../../lib/shengpay/lib/ShengPay.Client.php";
        require_once  dirname(__FILE__ ).'/'."/../../lib/shengpay/example/ShengPay.Config.php";
        $config = new ShengPayConfig();
        $request = new \UnifiedOrderRequest();
        $configs = self::shengPayConfig();
        $notify_url = $configs['notify_url'];
        $mchId = $configs['mchId'];//商户号
        $request->setTradeType('alipay_qr');
        $request->setBody($body);
        $request->setCurrency('CNY');
        $request->setNotifyUrl($notify_url);
        $request->setOutTradeNo($sn);
        $request->setTimeExpire(date('YmdHis')+900);
        $request->setTotalFee($money*100);
        $request->setClientIp($ip);
        $request->setMchId($mchId);
        $result = \ShengPayClient::execute($request, $config);
        $res = json_decode($result,true);
        if($res['resultCode'] == 'SUCCESS'){
            return ['code'=>200,'msg'=>'','data'=>$res['payInfo']];
        }else{
            return ['code'=>201,'msg'=>'支付拉取失败','data'=>$res];
        }
        
    }

    //云银支付 微信支付
    static function Yy_pay($money,$sn,$subject,$body,$ip){
        $config = self::YyConfig();
        $notify_url = $config['notify_url'];//请确保这个外部能访问
        $mchId = $config['mchNo'];//商户号
        $apiInfo = $config['apiInfo'];//预留信息
        $apiUrl = $config['apiUrl'];//远程下单地址

        $configs = [
            "mchNo"         => $mchId,//商户号
            "ifCode"        => 'wxpay',// 1微信 2支付宝
            "notifyUrl"     => $notify_url,//异步通知地址
            "mchOrderNo"    => $sn,//商户订单
            "apiInfo"       => $apiInfo,//商品名称
            "subject"       => $subject,//商品描述
            "body"          => $subject,
            "amount"	    => $money*100,//订单金额
        ];

//        $config['sign'] = (new PayUtils())->filter($config)->sort()->buildUrl()->sign($key);
//        $config['sign_type'] = $signType;

        $response = self::http_post_json($apiUrl,json_encode($configs));
        // $response = (new PayUtils())->getHttpResponse($apiUrl, http_build_query($configs));
        // print_r($response);exit;
        if(!$response) {
            return ['code'=>1,'msg'=>'访问失败'];
        }


        $result = json_decode($response, true);

        if($result['code'] !== 0) {
            return ['code'=>1,'msg'=>$result['msg'],'data'=>[]];
        }
        // print_r($result);exit;
        // $data['data']['result']['pay_url'] = $result['data']['qrUrl'];//返回小程序链接
        $data['data']['result']['qr_code'] = $result['data']['originalResponse']['payData'];//返回支付二维码
        return ['code'=>200,'msg'=>$data,'data'=>$result];
    }

    static function http_post_json($url, $jsonStr)
    {
        // print_r($jsonStr);exit;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $httpheader[] = "Accept: */*";
        $httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
        $httpheader[] = "Connection: close";
        $httpheader[] = "Content-Type: application/json";
        // $httpheader[] = "reserve: ".$reserve;
        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonStr);

        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    //  云银支付回调地址-微信支付-通道1
    public function Yy_notify() {
        $result = input('post.');
        $ip = request()->ip();
        self::writeLog(6,json_encode($result),'微信支付通道1回调日志 IP:'.$ip);
        if(!$result || !is_array($result) || $ip !== '8.134.117.143' ) { //8.138.12.86
            echo 'FAIL';
            exit;
        }
        $outTradeNo = $result['mchOrderNo'];
        if($result['state'] == 2) {
            $a = mb_substr($outTradeNo,0,2);
            if($a == 'JF'){//积分充值
                $order_info = Db::name('prestore_recharge')->where('order_sn',$outTradeNo)->find();
                if(!$order_info){
                    self::writeLog(6,'云银支付充值订单不存在：'.$outTradeNo);
                    exit('FAIL');
                }
                if($order_info['status']==1){
                    exit('FAIL');
                }
                $total_amount = $result['amount']/100;
                if($total_amount!=$order_info['amount']){
                    self::writeLog(7,'云银支付充值支付金额和订单金额不匹配：'.$outTradeNo);
                    exit('FAIL');
                }
                Db::name('prestore_recharge')->where('order_sn',$order_info['order_sn'])->update([
                    'status' => 1,
                    'pay_type' => 11,
                    'pay_time' => date('Y-m-d H:i:s')
                ]);
                Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                    'integral' => Db::raw('integral+' . $order_info['bal_amount'])
                ]);
                Db::name('bal_record')->insert([
                    'number'=>$order_info['bal_amount'],
                    'type'=>6,
                    'info'=>'积分充值',
                    'member_id'=>$order_info['member_id']
                ]);
                Push::main()->administration_commission($order_info['member_id'],$order_info['amount']);
                exit('success');
            }
            if($a == 'GW'){//商品购物
                $total_amount = $result['amount']/100;
                $order_info = Db::name('mall_order')->where('order_sn',$outTradeNo)->find();
                if(!$order_info){
                    self::writeLog(6,'云银支付购物订单不存在：'.$outTradeNo.json_encode($outTradeNo));
                    exit('FAIL');
                }
                if($order_info['order_status']==1 || $order_info['order_status']==3){
                    exit('success');
                }
                if($total_amount!=$order_info['order_amount']){
                    self::writeLog(7,'云银支付购物支付金额和订单金额不匹配：'.$outTradeNo);
                    exit('FAIL');
                }
                Db::name('mall_order')->where(['order_status' => 0, 'order_sn' => $outTradeNo])->update([
                    'order_status' => 1,
                    'amount'=>$total_amount,
                    'pay_time' => date('Y-m-d H:i:s'),
                    'pay_type' => 11,
                ]);
                if($order_info['goods_type'] == 5){
                    Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'is_vip'=>1
                    ]);
                    //给城市代理和驿站代理反佣
                    Push::main()->city_commission($order_info['member_id'],$total_amount);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$total_amount);
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                }
                //积分区
                if($order_info['goods_type'] == 6){
                    Push::main()->integral_rebate($total_amount,$order_info['member_id']);
                    Db::name('bal_record')->insert([
                        'number'=>round($total_amount*0.9,2),//消费积分赠送数量,
                        'type'=>6,
                        'info'=>'积分充值',
                        'member_id'=>$order_info['member_id']
                    ]);
                    Db::name('mall_order')->where(['order_main' => $outTradeNo])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                }
                if($order_info['goods_type'] == 7){//会员B区
                    Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                        'order_status' => 3,
                        'over_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'is_vip'=>1
                    ]);
                    //给城市代理和驿站代理反佣
                    Push::main()->city_commission($order_info['member_id'],$total_amount);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$total_amount);
                    //用户增加绿色积分
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                    //返佣直推二代
                    Push::main()->vipB($order_info['member_id'],$total_amount);
                }
                exit('success');
            }
        }
        else {
            self::writeLog(8, '云银支付充值验签失败：' . json_encode(($result)));
            echo 'FAIL';
            exit;
        }
    }

//微信支付通道2_START

    static function YyxConfig(){
        return [
            'mchId'     => 6714,//商户号
            'key'       => '6SiFzglP0F1Lhv1FyP1FUwM3HOaPd6X3',//应用密钥
            'signType'  => 'MD5',
            'reserve'   => '625841',//预留信息
            'apiUrl'    => 'http://pay.gxqhydf520.com/pay/',//远程下单地址
            'notify_url'=> 'https://shop.gxqhydf520.com/index/payment/Yyx_notify'//回调地址
        ];
    }
    
    static function Yyx_pay($money,$sn,$subject,$body,$ip){
        $config = self::YyxConfig();
        $notify_url = $config['notify_url'];//请确保这个外部能访问
        $return_url = '';

         $mchId = $config['mchId'];//商户号
         $key = $config['key'];//应用密钥
         $signType = $config['signType'];
         $apiUrl = $config['apiUrl'];//远程下单地址

        $config = [
            "mch_id"        => $mchId,//商户号
            "type"          => 1,// 1微信 2支付宝
            "notify_url"    => $notify_url,//异步通知地址
            "return_url"    => $return_url,//公众号支付如果没有参与点金计划，则返回该地址
            "out_trade_no"  => $sn,//商户订单
            "subject"       => $subject,//商品名称
            "body"          => $body,//商品描述
            "extra"         => '',//额外参数
            "amount"	    => $money,//订单金额
            "ip"            => $ip //用户发起支付的IP地址
        ];

        $config['sign'] = (new PayUtils())->filter($config)->sort()->buildUrl()->sign($key);
        $config['sign_type'] = $signType;

        $response = (new PayUtils())->getHttpResponse($apiUrl, http_build_query($config));
        self::writeLog(17,$response,'微信支付通道2获取支付码');

        if(!$response) {
            return ['code'=>1,'msg'=>'访问失败'];
        }


        $result = json_decode($response, true);

        if($result['code'] !== 200) {
            return ['code'=>1,'msg'=>$result['msg'],'data'=>$result['result']];
        }
        return ['code'=>1,'msg'=>'远程下单成功','data'=>$result];
    }
    
    public function Yyx_notify() {
        $result = input('post.');
        $config = $this->YyxConfig();
        $outTradeNo = $_GET['outTradeNo']??0;
        $amount = $_GET['amount']??0;
        $sn = $_GET['sn']??0;
        $ip = request()->ip();
        
        
        if($ip != '139.224.44.182'){
        self::writeLog(17,'非法IP访问_'.$ip.'_'.$outTradeNo.'_'.$amount.'_'.$sn,'微信支付通道2回调错误');
        exit('FAIL');
        }else{
	self::writeLog(17,$outTradeNo.'_'.$amount.'_'.$sn.'_'.$ip,'微信支付通道2回调');
        }
        
        $checkSign = true;
        if($checkSign) {
                $order_info = Db::name('prestore_recharge')->where(['order_sn' => $outTradeNo, 'status' => '0'])->find();
                $total_amount = $amount;
                if(intval($total_amount)!=intval($order_info['amount'])){
                    self::writeLog(17,'订单不存在 或 金额不匹配 或 已支付：'.$outTradeNo.'_'.$amount.'_'.$ip,'微信支付通道2回调错误');
                    exit('FAIL');
                }
                Db::name('prestore_recharge')->where('order_sn',$outTradeNo)->update([
                    'status' => 1,
                    'pay_type' => 17,
                    'pay_time' => date('Y-m-d H:i:s')
                ]);
                Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                    'integral' => Db::raw('integral+' . $order_info['bal_amount'])
                ]);
                Db::name('bal_record')->insert([
                    'number'=>$order_info['bal_amount'],
                    'type'=>6,
                    'info'=>'积分充值',
                    'member_id'=>$order_info['member_id']
                ]);
                Push::main()->administration_commission($order_info['member_id'],$order_info['amount']);
                exit('SUCCESS');
                
            
        }
        
    }
    
//微信支付通道2_END


    //杉德支付-H5快捷支付
    public function dopayment($order_no,$money,$goods_name,$user_id,$username,$ip){
        $config = self::shandePayConfig();
        $ip = str_replace('.','_',$ip);
        $data = [
            'version' => 10,
            'mer_no' =>  $config['mer_no'], //商户号
            'mer_order_no' => $order_no, //商户唯一订单号
            'create_time' => date('YmdHis'),
            'expire_time' => date('YmdHis', time()+30*60),
            'order_amt' => $money, //订单支付金额
            'notify_url' => $config['notify_url'], //订单支付异步通知
            'return_url' => $config['return_url'], //订单前端页面跳转地址
            'create_ip' => $ip,//格式ip中的点[.]替换为下划线[_]
            'goods_name' => $goods_name,
            'store_id' => $config['store_id'],
            'product_code' => $config['product_code'],
            'clear_cycle' => '3',
            //pay_extra参考语雀文档4.3
            'pay_extra' => json_encode(["userId"=>"$user_id","nickName"=>"$username","accountType"=>"1"]),
            'accsplit_flag' => 'NO',
            'jump_scheme' => '',
            'meta_option' => json_encode([["s" => "Android","n" => "wxDemo","id" => "com.pay.paytypetest","sc" => "com.pay.paytypetest"]]),
            'sign_type' => 'RSA'

        ];
        $temp = $data;
        unset($temp['goods_name']);
        unset($temp['jump_scheme']);
        unset($temp['expire_time']);
        unset($temp['product_code']);
        unset($temp['clear_cycle']);
        unset($temp['meta_option']);
        $sign = $this->sign($this->getSignContent($temp));
        $data['sign'] = $sign;
        $query = http_build_query($data) ;
        $payurl = "https://sandcash.mixienet.com.cn/pay/h5/fastpayment?".$query;  // 电子钱包【云账户】：cloud
        return $payurl; // 返回支付url
    }

    //杉德支付-聚合码
    public function dopayment_qrcode($order_no,$money,$goods_name,$user_id,$username,$ip){
        $config = self::shandePayConfig2();
        $ip = str_replace('.','_',$ip);
        $data = [
            'version' => 10,
            'mer_no' =>  $config['mer_no'], //商户号
            'mer_order_no' => $order_no, //商户唯一订单号
            'create_time' => date('YmdHis'),
            'expire_time' => date('YmdHis', time()+30*60),
            'order_amt' => $money, //订单支付金额
            'notify_url' => $config['notify_url'], //订单支付异步通知
            'return_url' => $config['return_url'], //订单前端页面跳转地址
            'create_ip' => $ip,//格式ip中的点[.]替换为下划线[_]
            'goods_name' => $goods_name,
            'store_id' => $config['store_id'],
            'product_code' => $config['product_code'],
            'clear_cycle' => '3',
            //pay_extra参考语雀文档4.3
            'pay_extra' => json_encode(["userId"=>"$user_id","nickName"=>"$username","accountType"=>"1"]),
            'accsplit_flag' => 'NO',
            'jump_scheme' => '',
            'meta_option' => json_encode([["s" => "Android","n" => "wxDemo","id" => "com.pay.paytypetest","sc" => "com.pay.paytypetest"]]),
            'sign_type' => 'RSA'

        ];
        $temp = $data;
        unset($temp['goods_name']);
        unset($temp['jump_scheme']);
        unset($temp['expire_time']);
        unset($temp['product_code']);
        unset($temp['clear_cycle']);
        unset($temp['meta_option']);
        $sign = $this->sign($this->getSignContent($temp));
        $data['sign'] = $sign;
        $query = http_build_query($data) ;
        $payurl = "https://sandcash.mixienet.com.cn/pay/h5/qrcode?".$query;  // 电子钱包【云账户】：cloud
        return $payurl; // 返回支付url
    }

    //杉德支付-收银台
    public function dopayment_h5($order_no,$money,$goods_name,$user_id)
    {
        $config = self::shandeH5PayConfig();
        $pay_money = $money*100;
        $s = strlen($pay_money);
        for($i=0;$i<12-$s;$i++){
            $pay_money = '0'.$pay_money;
        }
        $time = date('H:i');
        $week = date('w');
        $payModeList = "[qppay]";
        
        /*
        if($week>=1 && $week<5){//周一到周四
            if(($time>="00:00" && $time<="16:30") || ($time>="20:30" && $time<="23:59")){
                $payModeList = "[qppay,rempay]";
            }
        }
        if($week == 5){//周五
            if($time>="00:00" && $time<="16:30"){
                $payModeList = "[qppay,rempay]";
            }
        }
        if($week == 6){//周六
            $payModeList = "[qppay]";
        }
        if($week == 0){//周日
            if($time>="20:30" && $time<="23:59"){
                $payModeList = "[qppay,rempay]";
            }
        }
        */
        
        $params = [
            'userId' =>"$user_id",
            'orderCode' => $order_no,
            'totalAmount' => $pay_money,
            'subject' => '商品购买',
            'body' => '购买商品' . $goods_name,
            'notifyUrl' => $config['notify_url'],
            'frontUrl' => $config['return_url'],
            "extend" => "",
            "accsplitInfo" => "",
            "clearCycle" => "",
            'payModeList' => $payModeList,
            "txnTimeOut" => ""
        ];

        $client = new PCCashier();
        // 参数
        $client->body = $params ;
        $apiMap=$client->apiMap();
        $postData = $client->postData($apiMap['orderCreate']['method']);
        $res = Db::name('sande')->insertGetId(['data'=>json_encode($postData)]);
        return  ['data'=>$res];
    }

    public function shande_notify(){
        $result = input('post.');
        if(!$result || !is_array($result)) {
            echo 'FAIL';
            exit;
        }
        $data = htmlspecialchars_decode($result['data']);
        if($data){
            $ip = request()->ip();
            self::writeLog(6,$data,'杉德回调参数 ip:'.$ip);
            $res = json_decode($data,true);
            $outTradeNo = $res['body']['orderCode'];//订单号
            $orderStatus = $res['body']['orderStatus'];//订单状态 1-成功
            if($orderStatus == 1){
                $a = mb_substr($outTradeNo,0,2);
                if($a == 'JF'){//积分充值
                    $order_info = Db::name('prestore_recharge')->where('order_sn',$outTradeNo)->find();
                    if(!$order_info){
                        self::writeLog(6,'杉德支付充值订单不存在：'.$outTradeNo);
                        exit('FAIL');
                    }
                    if($order_info['status']==1){
                        exit('FAIL');
                    }
//                    $total_amount = $result['tranAmt'];
//                    if($total_amount!=$order_info['amount']){
//                        self::writeLog(7,'杉德支付充值支付金额和订单金额不匹配：'.$outTradeNo);
//                        exit('FAIL');
//                    }
                    Db::name('prestore_recharge')->where('order_sn',$order_info['order_sn'])->update([
                        'status' => 1,
                        'pay_type' => 12,
                        'pay_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'integral' => Db::raw('integral+' . $order_info['bal_amount'])
                    ]);
                    Db::name('bal_record')->insert([
                        'number'=>$order_info['bal_amount'],
                        'type'=>6,
                        'info'=>'积分充值',
                        'member_id'=>$order_info['member_id']
                    ]);
                    Push::main()->administration_commission($order_info['member_id'],$order_info['amount']);
                    exit('respCode=000000');
                }
                if($a == 'GW'){//商品购物
//                    $total_amount = $result['amount'];
                    $order_info = Db::name('mall_order')->where('order_sn',$outTradeNo)->find();
                    if(!$order_info){
                        self::writeLog(6,'杉德支付购物订单不存在：'.$outTradeNo.json_encode($outTradeNo));
                        exit('FAIL');
                    }
                    if($order_info['order_status']==1 || $order_info['order_status']==3){
                        exit('respCode=000000');
                    }
                    //接口中未返回支付金额，所以下面这段注释了
//                    if($total_amount!=$order_info['order_amount']){
//                        self::writeLog(7,'杉德支付购物支付金额和订单金额不匹配：'.$outTradeNo);
//                        exit('FAIL');
//                    }
                    Db::name('mall_order')->where(['order_status' => 0, 'order_sn' => $outTradeNo])->update([
                        'order_status' => 1,
                        'amount'=>$order_info['order_amount'],
                        'pay_time' => date('Y-m-d H:i:s'),
                        'pay_type' => 12,
                    ]);
                    if($order_info['goods_type'] == 5){
                        Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$order_info['order_amount']);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$order_info['order_amount']);
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                    }
                    //积分区
                    if($order_info['goods_type'] == 6){
                        Push::main()->integral_rebate($order_info['order_amount'],$order_info['member_id']);
                        Db::name('bal_record')->insert([
                            'number'=>round($order_info['order_amount']*0.9,2),//消费积分赠送数量,
                            'type'=>6,
                            'info'=>'积分充值',
                            'member_id'=>$order_info['member_id']
                        ]);
                        Db::name('mall_order')->where(['order_main' => $outTradeNo])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                    }
                    if($order_info['goods_type'] == 7){//会员B区
                        Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$total_amount);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$total_amount);
                        //用户增加绿色积分
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                        //返佣直推二代
                        Push::main()->vipB($order_info['member_id'],$total_amount);
                    }
                    exit('respCode=000000');
                }
            }
        }
    }
    
    //盛付通-回调
    public function shengpay_notify(){
        $ip = request()->ip();
        $result = file_get_contents("php://input");
	    self::writeLog(69,$result,'盛付通回调参数 ip:'.$ip);
	    
	    if($ip !== '211.147.71.129' && $ip !== '120.136.128.129') {
	        self::writeLog(69,$ip,'盛付通ip错误！');
            echo 'FAIL';
            exit;
        }
        
        $data = json_decode($result,true);
        if($data){
            $res = $data;
            $outTradeNo = $res['outTradeNo'];//订单号
            $orderStatus = $res['resultCode'];//订单状态 1-成功
            $tradeType = $res['tradeType'];//支付方式
            if($tradeType == 'wx_lite'){
                $pay_type = 15;
            }else{
                $pay_type = 16;
            }
            if($orderStatus == 'SUCCESS'){
                $a = mb_substr($outTradeNo,0,2);
                if($a == 'JF'){//积分充值
                    $order_info = Db::name('prestore_recharge')->where('order_sn',$outTradeNo)->find();
                    if(!$order_info){
                        self::writeLog(6,'盛付通充值订单不存在：'.$outTradeNo);
                        exit('FAIL');
                    }
                    if($order_info['status']==1){
                        exit('FAIL');
                    }
                    Db::name('prestore_recharge')->where('order_sn',$order_info['order_sn'])->update([
                        'status' => 1,
                        'pay_type' => $pay_type,
                        'pay_time' => date('Y-m-d H:i:s')
                    ]);
                    Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                        'integral' => Db::raw('integral+' . $order_info['bal_amount'])
                    ]);
                    Db::name('bal_record')->insert([
                        'number'=>$order_info['bal_amount'],
                        'type'=>6,
                        'info'=>'积分充值',
                        'member_id'=>$order_info['member_id']
                    ]);
                    Push::main()->administration_commission($order_info['member_id'],$order_info['amount']);
                    exit('SUCCESS');
                }
                if($a == 'GW'){//商品购物

                    $order_info = Db::name('mall_order')->where('order_sn',$outTradeNo)->find();
                    if(!$order_info){
                        self::writeLog(6,'盛付通购物订单不存在：'.$outTradeNo.json_encode($outTradeNo));
                        exit('FAIL');
                    }
                    if($order_info['order_status']==1 || $order_info['order_status']==3){
                        exit('SUCCESS');
                    }
                   
                    Db::name('mall_order')->where(['order_status' => 0, 'order_sn' => $outTradeNo])->update([
                        'order_status' => 1,
                        'amount'=>$order_info['order_amount'],
                        'pay_time' => date('Y-m-d H:i:s'),
                        'pay_type' => $pay_type,
                    ]);
                    if($order_info['goods_type'] == 5){
                        Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$order_info['order_amount']);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$order_info['order_amount']);
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                    }
                    //积分区
                    if($order_info['goods_type'] == 6){
                        Push::main()->integral_rebate($order_info['order_amount'],$order_info['member_id']);
                        Db::name('bal_record')->insert([
                            'number'=>round($order_info['order_amount']*0.9,2),//消费积分赠送数量,
                            'type'=>6,
                            'info'=>'积分充值',
                            'member_id'=>$order_info['member_id']
                        ]);
                        Db::name('mall_order')->where(['order_main' => $outTradeNo])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                    }
                    if($order_info['goods_type'] == 7){//会员B区
                        Db::name('mall_order')->where(['order_id' => $order_info['order_id']])->update([
                            'order_status' => 3,
                            'over_time' => date('Y-m-d H:i:s')
                        ]);
                        Db::name('member')->where(['id'=>$order_info['member_id']])->update([
                            'is_vip'=>1
                        ]);
                        //给城市代理和驿站代理反佣
                        Push::main()->city_commission($order_info['member_id'],$total_amount);
                        //增加业绩
                        Push::main()->get_merits($order_info['member_id'],$total_amount);
                        //用户增加绿色积分
                        Push::main()->members_rebate($order_info['member_id']);
                        //发放新人礼包
                        Push::main()->newMember($order_info['member_id']);
                        //返佣直推二代
                        Push::main()->vipB($order_info['member_id'],$total_amount);
                    }
                    exit('SUCCESS');
                }
            }
        }
    }

    function sign($str) {
        $config = self::shandePayConfig();
        $file = file_get_contents($config['privateKeyPath']);
        if (!$file) {
            throw new \Exception('loadPk12Cert::file
                    _get_contents');
        }
        if (!openssl_pkcs12_read($file, $cert, $config['privateKeyPwd'])) {
            throw new \Exception('loadPk12Cert::openssl_pkcs12_read ERROR');
        }
        $pem = $cert['pkey'];
        openssl_sign($str, $sign, $pem);
        $sign = base64_encode($sign);
        return $sign;
    }

    function getSignContent($params) {
        ksort($params);

        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {

                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }

        unset ($k, $v);
        return $stringToBeSigned;
    }

    function checkEmpty($value)
    {
        if (!isset($value))
            return true;
        if ($value === null)
            return true;
        if (trim($value) === "")
            return true;

        return false;
    }
}