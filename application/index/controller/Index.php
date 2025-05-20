<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2020/6/30
 * Time: 15:17
 */

namespace app\index\controller;

use alipay\aop\AopClient;
use app\lib\AES;
use app\lib\Efalipay;
use app\lib\YunZhong;
use \app\lib\JytPay\JytJsonClient;
use think\Controller;
use think\Db;

class Index extends Controller
{
    static public function randCreateOrderSn($user_id)
    {
        $capital = randStr(2, false, false);
        return $capital . $user_id . time();
    }
    /*
    public function index(){
        exit('红韵商城');
    }
    */
    public function index() {
        /* 
        $outTradeNo = $_GET['out_trade_no']??0;
        
            $a = mb_substr($outTradeNo,0,2);
            
            if($a == 'GW'){//商品购物
                $order_info = Db::name('mall_order')->where('order_sn',$outTradeNo)->find();
                
                if( $_GET['sn'] != 20241003 ){
                exit('秘钥不正确');
                }
                
                if(!$order_info){
                    exit('订单号不存在');
                    
                }
                if($order_info['order_status']==1 || $order_info['order_status']==3){
                    exit('已处理过');
                }
                
                Db::name('mall_order')->where(['order_status' => -1, 'order_sn' => $outTradeNo])->update([
                    'order_status' => 1,
                    'amount'=>$order_info['order_amount'],
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
                    Push::main()->city_commission($order_info['member_id'],$order_info['order_amount']);
                    //增加业绩
                    Push::main()->get_merits($order_info['member_id'],$order_info['order_amount']);
                    Push::main()->members_rebate($order_info['member_id']);
                    //发放新人礼包
                    Push::main()->newMember($order_info['member_id']);
                }
                
                exit('已处理');
            }
        */
    }

    public function pay_success(){
        return response($this->fetch());
    }
    
    public function pay(){
        $res = Db::name('sande')->where(['id'=>$_GET['data']])->find();
        $datas = json_decode($res['data'],true);
        $this->assign('data',['sign'=>$datas['sign'],'data'=>$datas['data']]);
        return response($this->fetch());
    }
       /**
     * 用户协议
     */
    public function custom_service(){
        return response($this->fetch());
    }
    public function auth_res(){
        return response($this->fetch('auth_res',['status'=>1,'notice'=>'签约成功','gid'=>111]));
    }
    /**
     * 注册网页版
     */
    public function reg(){
//        return response($this->fetch());
    }
    /**
     * 下载APP
     */
    public function down(){
        return response($this->fetch());
    }
    /**
     * 隐私政策
     */
    public function privacy_policy(){
        return response($this->fetch());
    }
}