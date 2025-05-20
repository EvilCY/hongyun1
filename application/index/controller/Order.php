<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2019/7/9
 * Time: 15:40
 */

namespace app\index\controller;
use AlibabaCloud\Client\Support\Path;
use app\lib\Curl;
use app\lib\Efalipay;
use think\Db;
use think\Exception;
class Order extends Base
{
    protected $totalMarketPrice = 0.00;//订单市场总价
    protected $totalPrice = 0.00;//订单商城总价
    protected $realPrice =0.00;//抵扣金额
    protected $coupon_id =0;//优惠券ID
    protected $goodsType =0;//订单商品类型

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
     * 确认下单模块
     *
     * @throws Exception
     */
    public function create()
    {
            #会员ID
            $aid = input('aid'); #收货地址
            $par = input('par'); #下单参数
            $where = array('member_id' => $this->member_id,'is_default' => 1);
            if ($aid) {
                $where = ['id' => $aid, 'member_id' => $this->member_id];
            }
            #查询收货信息
            $defAddress = Db::name('mall_address')->where($where)->find();
            if(!$defAddress){
                $defAddress = Db::name('mall_address')->where(['member_id' => $this->member_id])->find();
            }
            #分割多个产品id
            $product = $this->getParGoods($par);
            foreach ($product as $v) {
                if($v['goods_type'] == 4){
                    $this->response('赠送区商品只能用来赠送哦！');
                }
                if($v['goods_type']!=$this->goodsType){
                    $this->response('您购买的商品中同时存在'.config('goods_type')[$v['goods_type']].','.config('goods_type')[$this->goodsType].'商品，请先确认后再下单！');
                }
            }
            $this->response([
                'address' => $defAddress,
                'list' => $product,
                'totalMarketPrice' => $this->totalMarketPrice,
                'totalPrice' => $this->totalPrice,
                'realPrice' => $this->realPrice,
            ],true);
    }
    /**
     * 确认下单数据处理
     *
     * @throws Exception
     */
    public function docreate()
    {
        if (request()->isPost()) {
            $address_id = input('post.address_id');
            $user_id = $this->member_id;
            $store_id = input('post.store_id',0);#商户ID
            $par = input('post.par'); #下单参数
            if(!$address_id){
                $this->response('请添加收货地址');
            }
            $this->member_oplimit();
            $order_main = 'GW'.self::randCreateOrderSn($user_id);//生成订单号
            //解析商品计算价格
            $product = $this->getParGoods($par);
            if(!$this->userinfo['is_vip'] && $this->goodsType!=5){
                $this->response('请购买新人福包，升级为正式会员',false,299);
            }
            $amount = $this->totalPrice;//实付金额
            //抵扣金额
            $real_amount = $this->realPrice;//抵扣金额
            $address = Db::name('mall_address')->where(['id'=>$address_id])->find();
            
            //开启事物
            Db::startTrans();
            $orderRes = Db::name("mall_order_all")->insert([
                'member_id' => $user_id,//会员ID
                'order_sn' => $order_main,
                'order_amount' => $amount,//订单总金额
                'order_bal_amount' => $real_amount,//抵扣金额,
                'goods_type'=>$this->goodsType
            ]);
            if (!$orderRes) {
                $this->response('订单生成失败, 请稍后再试！');
            }
            $order_product = array();
            foreach ($product as $v) {
                $market_price = Db::name('mall_product_spec')->where(['spec_id' => $v['spec_id']])->find();
                if (!$v['status']) {
                    Db::rollback();
                    $this->response('下单商品中'.$v['title'].'【'.$v['spec_name'].'】已下架！请确认！');
                }
                if ($v['num'] > $v['stock']) {
                    Db::rollback();
                    $this->response('下单商品中'.$v['title'].'【'.$v['spec_name'].'】库存已不足！请确认！');
                }
                if ($v['spec_id'] and !$v['spec_name']) {
                    Db::rollback();
                    $this->response('下单商品中'.$v['title'].'下单商品中有商品规格参数有误！请确认！');
                    
                }
                if($v['goods_type']!=$this->goodsType){
                    Db::rollback();
                    $this->response('您购买的商品中同时存在'.config('goods_type')[$v['goods_type']].','.config('goods_type')[$this->goodsType].'商品，请先确认后再下单！');
                }
                if($v['goods_type'] == 5 && $this->userinfo['is_vip'] == 1){
                    Db::rollback();
                    $this->response('您已经购买过新人福包，请勿重复购买!');
                }
                if($v['goods_type'] == 5){
                    $has_order = Db::name('mall_order')
                    ->field('order_status, order_amount, add_time, goods_type, member_id')
                    ->where([
                        'member_id' => $user_id,
                        'goods_type' => '5',
                        'order_status' => '0'
                    ])->find();
                    if ($has_order) {
                        Db::rollback();
                        $this->response('您存在未付款的新人福包，请勿重复购买！');
                    };
                }
                if($v['goods_type'] == 5){
                    $v['total_price'] = rand(9800,9900)/100;
                }
                $order_product[] = [
                    'member_id' => $user_id,//会员ID
                    'order_main'=>$order_main,
                    'goods_id'=>$v['id'],
                    'num'=>$v['num'],
                    'spec_id'=>($v['spec_id'] ?: 0),
                    'order_sn' => 'GW'.self::randCreateOrderSn($user_id.$v['id']),
                    'order_amount' => $v['total_price'],//订单总金额
                    'order_bal_amount' => $real_amount,//抵扣金额
                    'address' => $address['address'],
                    'address_sub' => $address['address_sub'],
                    'tel' => $address['tel'],
                    'store_id'=>$store_id,
                    'name' => $address['uname'],
                    'goods_type'=>$this->goodsType,
                    'pay_mchid'=>$market_price['market_price'],
                ];
                //减库存
                    $restock[] = Db::name('mall_product_spec')->where(['spec_id' => $v['spec_id']])->update([
                        'stock' => Db::raw('stock-' . $v['num'])
                    ]);
                    $restock[] = Db::name('mall_product')->where(['id' => $v['id']])->update([
                        'stock' => Db::raw('stock-' . $v['num'])
                    ]);
                if (in_array(false,$restock)) {
                    Db::rollback();
                    $this->response('下单失败,请稍后再试!');
                }
            }
            if(count($product)>1){
                $order_sn = $order_main;
            }else{
                $order_sn = $order_product[0]['order_sn'];
            }
            if (!empty(input('post.cart_id'))) {
                $cart_id = explode(",",input('post.cart_id'));
                //清除购物车
                Db::name('mall_shopcar')->where([['member_id', '=', $user_id], ['id', 'in',$cart_id]])->delete();
            }
            $order_productRes = Db::name('mall_order')->insertAll($order_product);
            if (!$order_productRes) {
                Db::rollback();
                $this->response('下单失败,请稍后再试!');
            }
            //事物保存
            Db::commit();
            $this->response([
                'order_sn' => $order_sn,
                'amount' => ($amount-$real_amount),
                'real_amount' => $real_amount
            ],true);
        }
    }

    /**
     * 下单结果查询
     * @action
     */
    public function create_info(){
        $order_id = input('order_id');
        $user_id = $this->member_id;
        $userInfo = Db::name('member')->field('integral,points,green_points,lot,freeze_lot')->where(['id'=>$user_id])->find();
        $orderInfo = Db('mall_order')->where(['member_id'=>$user_id,'order_sn'=>$order_id])->field('order_sn,add_time,order_amount')->find();
        $this->response([
            'orderInfo'=>$orderInfo,
            'userInfo'=>$userInfo
        ],true);
    }
    /**
     * 下单结果查询
     * @action
     */
    public function create_info_all(){
        $order_id = input('order_id');
        $user_id = $this->member_id;
        $userInfo = Db::name('member')->field('integral,points,green_points,lot,freeze_lot')->where(['id'=>$user_id])->find();
        $orderInfo = Db('mall_order_all')->where(['member_id'=>$user_id,'order_sn'=>$order_id])->field('order_sn,add_time,order_amount')->find();
        $this->response([
            'orderInfo'=>$orderInfo,
            'userInfo'=>$userInfo
        ],true);
    }
    /**
     * 支付信息
     * @action
     */
    public function pay_info(){
        $order_id = input('get.order_sn',false);
        if(!$order_id){
            $this->response('系统错误');
        }
        $trade_info = Db::name('mall_order')->field('order_status,order_amount,add_time,goods_type,member_id')->where(['order_sn'=>$order_id])->find();
        $member_info = Db::name('member')->field('integral,points,green_points,lot,freeze_lot')->where(['id'=>$this->member_id])->find();
        if($trade_info['order_status']!=0){
            $this->response('订单状态异常');
        }
        if($trade_info['member_id']!=$this->member_id){
            $this->response('你没有该定单操作权限');
        }
        $end_time = date('Y-m-d H:i:s',strtotime($trade_info['add_time'])+900);
        $payment = [];
        $goods_type = $trade_info['goods_type'];
        switch ($goods_type){
            case 1:
                $payment[]= [
                    'value' => 1,
                    'account_bal' =>$member_info['integral'],
                    'label' => '消费积分'
                ];
                $payment[]= [
                    'value' => 2,
                    'account_bal' =>$member_info['lot'],
                    'label' => '福分(购物优先使用福分)'
                ];
                /*
                $payment[]= [
                    'value' => 3,
                    'label' => '微信'
                ];
                */
                
//                $payment[]= [
//                    'value' => 7,
//                    'label' => '易票联'
//                ];
                
                /*
                $payment[]= [
                    'value' => 9,
                    'label' => '支付宝-易票联'
                ];
                */
                /*
                $payment[]= [
                    'value' => 10,
                    'label' => '条码支付'
                ];
                */
                
                
                
                $payment[]= [
                    'value' => 8,
                    'label' => '快捷支付',
                    'bank_list'  =>Db::name('member_efalipay')->field('id,bank_name,bank_code')->where(['member_id'=>$this->member_id,'status'=>1])->select()
                ];
                
                $payment[]= [
                    'value' => 12,
                    'label' => '杉德支付'
                ];
                /*
                $payment[]= [
                    'value' => 11,
                    'label' => '微信支付-通道1'
                ];
                
                */
                
                
                $payment[]= [
                    'value' => 4,
                    'label' => '支付宝'
                ];
                
                $payment[]= [
                    'value' => 14,
                    'label' => '杉德收银台'
                ];
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
                
		        /*
                $payment[]= [
                    'value' => 13,
                    'label' => '杉德聚合'
                ];
                */
                break;
            case 2:
                $payment[]= [
                    'value' => 1,
                    'account_bal' =>$member_info['integral'],
                    'label' => '消费积分'
                ];
                $payment[]= [
                    'value' => 2,
                    'account_bal' =>$member_info['lot'],
                    'label' => '福分(购物优先使用福分)'
                ];
                break;
            case 3:
                $payment[]= [
                    'value' => 5,
                    'pick_list' =>Db::name('machine_pick')->field('id,price')->where("member_id = $this->member_id and status =1 and active_time >DATE_SUB(NOW(),INTERVAL 50 day) ")->select(),
                    'label' => '提货券'
                ];
                $payment[]= [
                    'value' => 6,
                    'pick_list' =>Db::name('coupon_pick')->field('id,money')->where(['status'=>1,'member_id'=>$this->member_id])->select(),
                    'label' => '权益券'
                ];
                break;
            case 5:  /*新人福包*/
                /*
                $payment[]= [
                    'value' => 3,
                    'label' => '微信'
                ];
                */
                
                
                $payment[]= [
                    'value' => 11,
                    'label' => '微信支付-通道1'
                ];
                
                $payment[]= [
                    'value' => 4,
                    'label' => '支付宝'
                ];/*
                */
//                $payment[]= [
//                    'value' => 7,
//                    'label' => '易票联'
//                ];
                
                $payment[]= [
                    'value' => 8,
                    'label' => '快捷支付',
                    'bank_list'  =>Db::name('member_efalipay')->field('id,bank_name,bank_code')->where(['member_id'=>$this->member_id,'status'=>1])->select()
                ];
                
                $payment[]= [
                    'value' => 14,
                    'label' => '杉德收银台'
                ];
                
                $payment[]= [
                    'value' => 12,
                    'label' => '杉德支付'
                ];
                
		        $payment[]= [
                    'value' => 15,
                    'label' => '盛付通-微信'
                ];
                 $payment[]= [
                    'value' => 16,
                    'label' => '盛付通-支付宝'
                ];
                /*
                */
                
                /*
                $payment[]= [
                    'value' => 9,
                    'label' => '支付宝-易票联'
                ];
                */
                /*
                $payment[]= [
                    'value' => 10,
                    'label' => '条码支付'
                ];
                */
                break;
            case 6:
                /*
                $payment[]= [
                    'value' => 3,
                    'label' => '微信'
                ];
                */
                
                /*
                $payment[]= [
                    'value' => 9,
                    'label' => '支付宝-易票联'
                ];
                */
                
                $payment[]= [
                    'value' => 10,
                    'label' => '条码支付'
                ];
                /*
                
                */
                $payment[]= [
                    'value' => 8,
                    'label' => '快捷支付',
                    'bank_list'  =>Db::name('member_efalipay')->field('id,bank_name,bank_code')->where(['member_id'=>$this->member_id,'status'=>1])->select()
                ];
                $payment[]= [
                    'value' => 14,
                    'label' => '杉德收银台'
                ];
                $payment[]= [
                    'value' => 12,
                    'label' => '杉德支付'
                ];
                /*
                $payment[]= [
                    'value' => 11,
                    'label' => '微信支付-通道1'
                ];
                
                */
                
                
                $payment[]= [
                    'value' => 4,
                    'label' => '支付宝'
                ];
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
                /*
                $payment[]= [
                    'value' => 13,
                    'label' => '杉德聚合'
                ];
                */
                
                /*
                */
                break;
            case 7:  /*新人福包*/
                /*
                $payment[]= [
                    'value' => 3,
                    'label' => '微信'
                ];
                */


                $payment[]= [
                    'value' => 11,
                    'label' => '微信支付-通道1'
                ];

                $payment[]= [
                    'value' => 4,
                    'label' => '支付宝'
                ];/*
                */
//                $payment[]= [
//                    'value' => 7,
//                    'label' => '易票联'
//                ];

                $payment[]= [
                    'value' => 8,
                    'label' => '快捷支付',
                    'bank_list'  =>Db::name('member_efalipay')->field('id,bank_name,bank_code')->where(['member_id'=>$this->member_id,'status'=>1])->select()
                ];

                $payment[]= [
                    'value' => 14,
                    'label' => '杉德收银台'
                ];

                $payment[]= [
                    'value' => 12,
                    'label' => '杉德支付'
                ];

                $payment[]= [
                    'value' => 15,
                    'label' => '盛付通-微信'
                ];
                $payment[]= [
                    'value' => 16,
                    'label' => '盛付通-支付宝'
                ];
                 break;
            case 8:  /*新人福包*/
                /*
                $payment[]= [
                    'value' => 3,
                    'label' => '微信'
                ];
                */


                $payment[]= [
                    'value' => 11,
                    'label' => '微信支付-通道1'
                ];

                $payment[]= [
                    'value' => 4,
                    'label' => '支付宝'
                ];/*
                */
//                $payment[]= [
//                    'value' => 7,
//                    'label' => '易票联'
//                ];

                $payment[]= [
                    'value' => 8,
                    'label' => '快捷支付',
                    'bank_list'  =>Db::name('member_efalipay')->field('id,bank_name,bank_code')->where(['member_id'=>$this->member_id,'status'=>1])->select()
                ];

                $payment[]= [
                    'value' => 14,
                    'label' => '杉德收银台'
                ];

                $payment[]= [
                    'value' => 12,
                    'label' => '杉德支付'
                ];

                $payment[]= [
                    'value' => 15,
                    'label' => '盛付通-微信'
                ];
                $payment[]= [
                    'value' => 16,
                    'label' => '盛付通-支付宝'
                ];
                 break;
            default:
                $payment[] = [];
                break;

        }
        $this->response([
            'order_info' => $trade_info,
            'member_info'=>$member_info,
            'payment' => $payment,
            'end_time' => $end_time
        ],true);
    }
    /**
     * 支付信息
     * @action
     */
    public function pay_info_all(){
        $order_id = input('get.order_sn',false.'intval');
        if(!$order_id){
            $this->response('系统错误');
        }
        $trade_info = Db::name('mall_order_all')->field('order_status,order_amount,add_time,goods_type,member_id')->where(['order_sn'=>$order_id])->find();
        $member_info = Db::name('member')->field('integral,points,green_points,lot,freeze_lot')->where(['id'=>$this->member_id])->find();
        if($trade_info['order_status']!=0){
            $this->response('订单状态异常');
        }
        if($trade_info['member_id']!=$this->member_id){
            $this->response('你没有该定单操作权限');
        }
        $end_time = date('Y-m-d H:i:s',strtotime($trade_info['add_time'])+900);
        $payment = [];
        $goods_type = $trade_info['goods_type'];
        switch ($goods_type){
            case 1:
                $payment[]= [
                    'value' => 1,
                    'account_bal' =>$member_info['integral'],
                    'label' => '消费积分'
                ];
                $payment[]= [
                    'value' => 2,
                    'account_bal' =>$member_info['lot'],
                    'label' => '福分(购物优先使用福分)'
                ];
                /*
                $payment[]= [
                    'value' => 3,
                    'label' => '微信'
                ];
                */
                
                /*
                $payment[]= [
                    'value' => 9,
                    'label' => '支付宝-易票联'
                ];
                */
                /*
                $payment[]= [
                    'value' => 10,
                    'label' => '条码支付'
                ];
                
                
                */
                $payment[]= [
                    'value' => 8,
                    'label' => '快捷支付',
                    'bank_list'  =>Db::name('member_efalipay')->field('id,bank_name,bank_code')->where(['member_id'=>$this->member_id,'status'=>1])->select()
                ];
                $payment[]= [
                    'value' => 14,
                    'label' => '杉德收银台'
                ];
                
                $payment[]= [
                    'value' => 12,
                    'label' => '杉德支付'
                ];
                /*
                $payment[]= [
                    'value' => 11,
                    'label' => '微信支付-通道1'
                ];
                
                */
                
                $payment[]= [
                    'value' => 4,
                    'label' => '支付宝'
                ];
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
		        
                /*
                $payment[]= [
                    'value' => 13,
                    'label' => '杉德聚合'
                ];
                */
                
                /*
                */
                break;
            case 2:
                $payment[]= [
                    'value' => 1,
                    'account_bal' =>$member_info['integral'],
                    'label' => '消费积分'
                ];
                $payment[]= [
                    'value' => 2,
                    'account_bal' =>$member_info['lot'],
                    'label' => '福分(购物优先使用福分)'
                ];
                break;
            case 3:
                $payment[]= [
                    'value' => 5,
                    'pick_list' =>Db::name('machine_pick')->field('id,price')->where("member_id = $this->member_id and status =1 and active_time >DATE_SUB(NOW(),INTERVAL 50 day) ")->select(),
                    'label' => '提货券'
                ];
                $payment[]= [
                    'value' => 6,
                    'pick_list' =>Db::name('coupon_pick')->field('id,money,title')->where(['status'=>1,'member_id'=>$this->member_id])->select(),
                    'label' => '权益券'
                ];
                break;
            case 5:
                /*
                $payment[]= [
                    'value' => 3,
                    'label' => '微信'
                ];
                */
                
                /*
                $payment[]= [
                    'value' => 9,
                    'label' => '支付宝-易票联'
                ];
                */
                /*
                $payment[]= [
                    'value' => 10,
                    'label' => '条码支付'
                ];
                
                
                */
                $payment[]= [
                    'value' => 8,
                    'label' => '快捷支付',
                    'bank_list'  =>Db::name('member_efalipay')->field('id,bank_name,bank_code')->where(['member_id'=>$this->member_id,'status'=>1])->select()
                ];
                $payment[]= [
                    'value' => 14,
                    'label' => '杉德收银台'
                ];
                $payment[]= [
                    'value' => 12,
                    'label' => '杉德支付'
                ];
                /*
                $payment[]= [
                    'value' => 11,
                    'label' => '微信支付-通道1'
                ];
                
                */
                
                $payment[]= [
                    'value' => 4,
                    'label' => '支付宝'
                ];
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
		
                /*
                $payment[]= [
                    'value' => 13,
                    'label' => '杉德聚合'
                ];
                */
                
                /*
                */
                break;
            case 6:
                /*
                $payment[]= [
                    'value' => 3,
                    'label' => '微信'
                ];
                */
                
                /*
                $payment[]= [
                    'value' => 9,
                    'label' => '支付宝-易票联'
                ];
                */
                /*
                $payment[]= [
                    'value' => 10,
                    'label' => '条码支付'
                ];
                
                
                */
                $payment[]= [
                    'value' => 8,
                    'label' => '快捷支付',
                    'bank_list'  =>Db::name('member_efalipay')->field('id,bank_name,bank_code')->where(['member_id'=>$this->member_id,'status'=>1])->select()
                ];
                $payment[]= [
                    'value' => 14,
                    'label' => '杉德收银台'
                ];
                $payment[]= [
                    'value' => 12,
                    'label' => '杉德支付'
                ];
                /*
                $payment[]= [
                    'value' => 11,
                    'label' => '微信支付-通道1'
                ];
                
                */
                
                $payment[]= [
                    'value' => 4,
                    'label' => '支付宝'
                ];
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
		
                /*
                $payment[]= [
                    'value' => 13,
                    'label' => '杉德聚合'
                ];
                */
                
                /*
                */
                break;
            default:
                $payment[] = [];
                break;

        }
        $this->response([
            'order_info' => $trade_info,
            'member_info'=>$member_info,
            'payment' => $payment,
            'end_time' => $end_time
        ],true);
    }
    /**
     * 根据指定订单参数解析查询商品
     * @param string $par 拆分数据字符串
     * @throws Exception
     * @return array
     * */
    protected function getParGoods($par)
    {
        $parArr = explode(',', $par);
        $product = [];

        foreach ($parArr as $k => $v) {
            $val = array_map('floor', explode(':', $v));
            if (count($val) > 3) {
                throw new Exception('下单参数有误！');
            }
            if ($val[0] <= 0)
                throw new Exception('下单参数有误！');
            if ($val[1] <= 0)
                throw new Exception('下单参数有误！');
            if($val[2]<= 0){
                throw new Exception('下单参数有误！');
            }
            $pro = Db::name('mall_product p')->field('spec.spec_id,spec.name as spec_name,id,imglogo,title,spec.price,spec.market_price,spec.stock,status,p.goods_type')
                ->leftJoin("mall_product_spec spec","spec.spec_id='{$val[2]}' and spec.product_id=p.id")
                ->where(['id' => $val[0]])->find();
            $pro['num'] = $val[1];
            $pro['total_price'] = round(($val[1] * $pro['price']),2);
            $this->goodsType = $pro['goods_type'];
            $product[] = $pro;
            //计算市场总价
            $this->totalMarketPrice += round($val[1] * $pro['market_price'],2);
            //计算商品总价
            $this->totalPrice += round(($val[1] * $pro['price']),2);
        }
        return $product;
    }
    /**
     * 订单列表
     * @action
     * */
    public function order_list()
    {
        $member_id = $this->member_id;
        $order_status = input('get.status', '', 'intval');
        $page = input('get.page',1,'intval');
        $where = [
            ['o.member_id', 'eq', $member_id,],
            ['o.is_off', 'eq', 0],
            ['o.order_status', 'neq', -1]
        ];
        if (input('status') != 'all') {
            $where[] = ['o.order_status', 'eq', $order_status];
        }
        $limit = 20;
        $orderList = Db::name('mall_order o')->field('o.add_time,o.amount,o.goods_type,o.order_amount,o.order_id,o.tran_sn,o.order_sn,o.order_status,o.num,p.title,p.imglogo,spec.name,spec.price,p.id')->leftJoin('mall_product p', 'p.id=o.goods_id')->leftJoin('mall_product_spec spec', 'spec.spec_id=o.spec_id')->limit(($page-1)*$limit,$limit)->where($where)->order('o.order_id desc')->group('o.order_id')->select();
        $total = Db::name('mall_order o')->where($where)->count('order_id');
        if ($orderList) {
            foreach ($orderList as $k => $v) {
                $orderList[$k]['status_describe'] = config('order_status_info')[$v['order_status']];
            }
        }
        $this->response([
            'totalPage' => ceil($total/$limit),
            'list' => $orderList
        ],true);
    }
    /**
     * 订单详情
     * @action
     * */
    public function order_info()
    {
        $order_id = input('get.order_id');
        $orderinfo = Db::name('mall_order o')->field('o.*,p.title,p.imglogo,spec.name,spec.price')->leftJoin('mall_product p', 'p.id=o.goods_id')->leftJoin('mall_product_spec spec', 'spec.spec_id=o.spec_id')->whereOr(['o.order_id' => $order_id, 'o.order_sn' => $order_id])->find();
        if($orderinfo){
            if($orderinfo['tran_sn']){
                $expo = explode(';',$orderinfo['tran_sn']);
                foreach ($expo as $item){
                    $orderinfo['tran_sn_list'][] = $item;
                }

            }else{
                $orderinfo['tran_sn_list'] = [];
            }
            $this->response($orderinfo,true);
        }else{
            $this->response('没有相关订单信息');
        }

    }

    /**
     * 确认收货
     * @ajax
     * */
    public function confirm_order()
    {
        
        //跳过BUG
        $this->response('系统正在自动收货');
        //跳过BUG
        
        /*
        if (request()->isPost()) {
            $user_id = $this->member_id;
            $order_id = input('post.order_id');
            $orderInfo = Db::name('mall_order')->lock(true)->where(['member_id' => $user_id, 'order_id' => $order_id])->find();
            if(empty($orderInfo)){
                $this->response('没有相关订单信息');
            }
            if($orderInfo['order_status']!=2){
                $this->response('该订单不支持此操作方式');
            }
            Db::startTrans();
            try {
                $total_amount = $orderInfo['order_amount'];
                Db::name('mall_order')->where(['member_id' => $user_id, 'order_id' => $order_id, 'order_status' => 2])->update([
                    'order_status' => 3,
                    'over_time' => date('Y-m-d H:i:s')
                ]);
                //给城市代理和驿站代理反佣
                Push::main()->city_commission($this->member_id,$total_amount);
                //增加业绩
                Push::main()->get_merits($this->member_id,$total_amount);
                //精选区购物返佣
                if($orderInfo['goods_type'] == 2){
                    Push::main()->choice_rebate($total_amount,$this->member_id);
                }
                //会员区购物返佣
                if($orderInfo['goods_type'] == 5){
                    Push::main()->members_rebate($this->member_id);
                }
                //会员区购物返佣
                if($orderInfo['goods_type'] == 1){
                    Push::main()->common_rebate($total_amount,$this->member_id);
                }
//                //积分区
//                if($orderInfo['goods_type'] == 6){
//                    Push::main()->integral_rebate($total_amount,$this->member_id);
//                }
                Db::commit();
                $this->response('确认收货成功',true);
            }catch (Exception $exception){
                Db::rollback();
                $this->response('收货失败，请稍后再试！');
            }
        }
        */
    }

    /**
     * 取消订单
     * @ajax
     * */
    public function cancel_order(){
        if (request()->isPost()) {
            Db::startTrans();
            $user_id = $this->member_id;
            $order_id = input('post.order_id');
            $orderInfo = Db::name('mall_order')->lock(true)->where(['member_id' => $user_id, 'order_id' => $order_id])->find();
            if(empty($orderInfo)){
                Db::rollback();
                $this->response('订单不存在');
            }
            if($orderInfo['order_status']!=0){
                Db::rollback();
                $this->response('该订单不支持此操作方式');
            }
            $orderRes[] = Db::name('mall_order')->where(['member_id' => $user_id, 'order_id' => $order_id])->update([
                'order_status' => -1
            ]);
            //还原库存
            $orderRes[] = Db::name('mall_product_spec')->where(['spec_id'=>$orderInfo['spec_id']])->update([
                'stock'=>Db::raw('stock+'.$orderInfo['num'])
            ]);
            $orderRes[] = Db::name('mall_product')->where(['id'=>$orderInfo['goods_id']])->setInc('stock',$orderInfo['num']);

            if(in_array(false,$orderRes)) {
                Db::rollback();
                $this->response('取消失败，请稍后再试！');
            }else{
                Db::commit();
                $this->response([
                    'msg'=>'取消成功',
                    'order_id'=>$order_id
                ],true);
            }
        }
    }
    /**
     * 订单退款
     * @action
     *
     */
    public function orders_return(){
        $order_id = input('post.order_id',false,'intval');
        if(!$order_id){
            $this->response('系统繁忙');
        }
        $info = Db::name('mall_order')->field('status')->where(['member_id'=>$this->member_id,'id'=>$order_id])->find();
        if(!$info){
            $this->response('未找到相关订单');
        }
        if($info['status']!=2){
            $this->response('订单状态有误');
        }
        Db::name('mall_order')->where(['id'=>$order_id])->update([
            'status' => 4,
            'update_time' => date('Y-m-d H:i:s')
        ]);
        $this->response('操作成功',true);
    }
    /**
     * 取消订单
     * @ajax
     * */
    public function del_order(){
        if (request()->isPost()) {
            Db::startTrans();
            $user_id = $this->member_id;
            $order_id = input('post.order_id');
            $orderInfo = Db::name('mall_order')->lock(true)->where(['member_id' => $user_id, 'order_id' => $order_id])->find();
            if(empty($orderInfo)){
                Db::rollback();
                $this->response('订单不存在');
            }
            if($orderInfo['order_status']!=-1 and $orderInfo['order_status']!=3){
                Db::rollback();
                $this->response('该订单不支持此操作方式');
            }
            $orderRes = Db::name('mall_order')->where(['member_id' => $user_id, 'order_id' => $order_id])->update([
                'is_off' => 1
            ]);
            if($orderRes) {
                Db::commit();
                $this->response('删除成功',true);

            }else{
                Db::rollback();
                $this->response('删除失败，请稍后再试！');
            }
        }
    }
    /**
     * 订单支付
     * @action
     */
    public function order_pay()
    {
        if (request()->isPost()){
            $this->member_oplimit();
            $order_sn = input('post.order_sn');
            $pay_pwd = input('post.pay_pwd');
            $pay_type = input('post.pay_type');
            if(in_array($pay_type,[1,2])){
                if(!$this->userinfo['pay_pwd']){
                    $this->response('您还未设置支付密码',false,-4);
                }
                if(!preg_match('/^\d{6}$/',$pay_pwd)){
                    $this->response('支付密码有误');
                }
                if(!$this->checkPaypwd($pay_pwd)){
                    $this->response('支付密码有误');
                }
            }
            $orderInfo = Db::name('mall_order')->lock(true)->where(['order_sn' => $order_sn])->find();
            if (!$orderInfo) {
                $this->response("订单编号({$order_sn})的订单不存在!");
            }
            if (!$orderInfo or $orderInfo['order_status'] < 0) {
                $this->response("订单编号({$order_sn})的订单不存在或已失效!");
            }
            if ($orderInfo['order_status'] > 0) {
                //支付后还在发送请求通知 给出success 防止支付方一直发请求
                $this->response("该订单已经付款");
            }

            $user_id = $this->member_id;
            $userInfo = Db::name('member')->where(['id' => $user_id])->field('id,integral,points,green_points,lot,freeze_lot,nickname')->find();
            $amount =$orderInfo['order_amount'];
            Db::startTrans();
            switch ($pay_type){
                case 1://消费积分支付
                    if ($userInfo['integral'] < $amount) {
                        $this->response('您的消费积分余额不足!');
                    }
                    try {
                        //更改订单状态
                        Db::name('mall_order')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                            'order_status' => 1,
                            'amount' => $amount,
                            'pay_time' => date('Y-m-d H:i:s'),
                            'pay_type' => $pay_type,
                        ]);
                        
                        Db::name('member')->where(['id' => $orderInfo['member_id']])->update([
                            'integral' => Db::raw('integral-' . $amount),
                        ]);
                        //添加余额记录
                        Db::name('bal_record')->insert([
                            'number' => -1 * $amount,
                            'type' => 10,
                            'info' => '商城购物消耗',
                            'member_id' => $orderInfo['member_id']
                        ]);
                        
                        //精选区购物返佣
                        if($orderInfo['goods_type'] == 2){
                            Push::main()->choice_rebate($amount,$orderInfo['member_id']);
                        }
                        //增加销量
                        $order_product = Db::name('mall_order')->where(['order_sn' => $order_sn])->select();
                        foreach ($order_product as $v) {
                            Db::name('mall_product_spec')->where(['spec_id' => $v['spec_id']])->setInc('sales', $v['num']);
                            Db::name('mall_product')->where(['id' => $v['goods_id']])->setInc('sales', $v['num']);
                        }
                        Db::commit();
                        $this->response([
                            'order_sn'=>$orderInfo['order_sn'],
                            'msg'=>'支付成功'
                        ],true);
                    }catch (Exception $exception){
                            Db::rollback();
                            self::writeLog(21,'订单'.$order_sn.'积分支付失败',$exception->getMessage());
                            $this->response('支付失败，请稍后再试');
                        }

                    break;
                case 2://福分支付
                    if ($userInfo['lot'] < $amount) {
                        $this->response('您的消费积分余额不足!');
                    }
                    try {
                        
                    //更改订单状态
                    Db::name('mall_order')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                        'order_status' => 1,
                        'amount'=>$amount,
                        'pay_time' => date('Y-m-d H:i:s'),
                        'pay_type' => $pay_type,
                    ]);
                    Db::name('member')->where(['id' => $orderInfo['member_id']])->update([
                        'lot'=>Db::raw('lot-'.$amount),
                    ]);
                    //添加余额记录
                    Db::name('bal_record')->insert([
                        'number'=>-1*$amount,
                        'type'=>11,
                        'info'=>'商城购物消耗',
                        'member_id'=>$orderInfo['member_id']
                    ]);
                    
                    //精选区购物返佣
                    if($orderInfo['goods_type'] == 2){
                        Push::main()->choice_rebate($amount,$orderInfo['member_id']);
                    }
                    //增加销量
                    $order_product = Db::name('mall_order')->where(['order_sn' => $order_sn])->select();
                    foreach ($order_product as $v) {
                        Db::name('mall_product_spec')->where(['spec_id' => $v['spec_id']])->setInc('sales', $v['num']);
                        Db::name('mall_product')->where(['id' => $v['goods_id']])->setInc('sales', $v['num']);
                    }
                    Db::commit();
                    $this->response([
                        'order_sn'=>$orderInfo['order_sn'],
                        'msg'=>'支付成功'
                    ],true);
                    }catch (Exception $exception){
                        Db::rollback();
                        self::writeLog(22,'订单'.$order_sn.'福分支付失败',$exception->getMessage());
                        $this->response('支付失败，请稍后再试');
                    }
                    break;
                case 3://微信支付
//                    $notify_url = 'https://suwen.dhlshu.cn/payment/renzhengHuidiao';   //自定义回调地址
                    $notify_url = 'https://shop.gxqhydf520.com/index/payment/wx_goods_return';   //测试回调地址
                    $str = Payment::openCloudWeixin($orderInfo['order_sn'],$amount,$notify_url);
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 4://支付宝支付
//                            $notify_url = 'http://hongyun.twen.ltd/index/payment/alipay_goods_return';//正式回调地址
                    $notify_url = 'https://shop.gxqhydf520.com/index/payment/alipay_goods_return';//测试回调地址
                    $str = Payment::alipay_param($amount,$orderInfo['order_sn'],'商城购物订单：HY'.$randomNumber = mt_rand(17777777, 77777777),'商品',$notify_url);
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 5://提货券支付
                    $coupon_id = input('post.coupon_id',0);
                    if(!$coupon_id){
                        $this->response('参数错误');
                    }
                    $couponInfo = Db::name('machine_pick')->where("member_id = $this->member_id and id = $coupon_id and status =1 and active_time >DATE_SUB(NOW(),INTERVAL 50 day) ")->find();
                    if(!$couponInfo){
                        $this->response('无效的提货券');
                    }
                    try {
                        //更改订单状态
                        Db::name('mall_order')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                            'order_status' => 1,
                            'amount' => 0,
                            'order_bal_amount' => $amount,
                            'coupon_id'=>$coupon_id,
                            'pay_time' => date('Y-m-d H:i:s'),
                            'pay_type' => $pay_type,
                        ]);
                        //增加销量
                        $order_product = Db::name('mall_order')->where(['order_sn' => $order_sn])->select();
                        foreach ($order_product as $v) {
                            Db::name('mall_product_spec')->where(['spec_id' => $v['spec_id']])->setInc('sales', $v['num']);
                            Db::name('mall_product')->where(['id' => $v['goods_id']])->setInc('sales', $v['num']);
                        }
                        Db::name('machine_pick')->where(['id'=>$coupon_id])->update([
                            'status'=>2
                        ]);
                        Db::commit();
                        $this->response([
                            'order_sn'=>$orderInfo['order_sn'],
                            'msg'=>'支付成功'
                        ],true);
                    }catch (Exception $exception){
                        Db::rollback();
                        self::writeLog(21,'订单'.$order_sn.'支付失败，支付方式：'.$pay_type.'失败原因：'.$exception->getMessage());
                        $this->response('支付失败，请稍后再试');
                    }
                    break;
                case 6://权益券支付
                    $coupon_id = input('post.coupon_id',0);
                    if(!$coupon_id){
                        $this->response('参数错误');
                    }
                    $couponInfo = Db::name('coupon_pick')->where(['id'=>$coupon_id,'member_id'=>$this->member_id,'status'=>1])->find();
                    if(!$couponInfo){
                        $this->response('无效的权益券');
                    }
                    if($couponInfo['goods_id']!=$orderInfo['goods_id']){
                        $goods_name = Db::name('mall_product')->where(['id'=>$orderInfo['goods_id']])->value('title');
                        $this->response('该权益券只能购买【'.$goods_name.'】');
                    }
                    try {
                        //更改订单状态
                        Db::name('mall_order')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                            'order_status' => 1,
                            'amount' => 0,
                            'order_bal_amount' => $amount,
                            'coupon_id'=>$coupon_id,
                            'pay_time' => date('Y-m-d H:i:s'),
                            'pay_type' => $pay_type,
                        ]);
                        //增加销量
                        $order_product = Db::name('mall_order')->where(['order_sn' => $order_sn])->select();
                        foreach ($order_product as $v) {
                            Db::name('mall_product_spec')->where(['spec_id' => $v['spec_id']])->setInc('sales', $v['num']);
                            Db::name('mall_product')->where(['id' => $v['goods_id']])->setInc('sales', $v['num']);
                        }
                        //改变权益券状态
                        Db::name('coupon_pick')->where(['id'=>$coupon_id])->update([
                            'status'=>2
                        ]);
                        Db::commit();
                        $this->response([
                            'order_sn'=>$orderInfo['order_sn'],
                            'msg'=>'支付成功'
                        ],true);
                    }catch (Exception $exception){
                        Db::rollback();
                        self::writeLog(21,'订单'.$order_sn.'支付失败，支付方式：'.$pay_type.'失败原因：'.$exception->getMessage());
                        $this->response('支付失败，请稍后再试');
                    }
                    break;
                case 7://易票联支付
                    $notify_url = 'https://shop.gxqhydf520.com/index/payment/ef_goods_return';//正式回调地址
//                  $notify_url = 'http://hongyun.twen.ltd/index/payment/ef_goods_return';//测试回调地址
                    $efalipay = new Efalipay();
                    $str = $efalipay->payPal($orderInfo['order_sn'],$amount,$notify_url);
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 8://快捷支付
                    $id = input('card_id');
                    $card = Db::name('member_efalipay')->where(['id'=>$id,'member_id'=>$this->member_id,'status'=>1])->find();
                    if(!$card){
                        $this->response('没有此银行卡信息');
                    }
                    $order_sn = 'GW'.self::randCreateOrderSn($user_id);
                    Db::name('mall_order')->where(['order_sn'=>$orderInfo['order_sn']])->update([
                        'order_sn'=>$order_sn
                    ]);

//                    $notify_url = 'https://shop.gxqhydf520.com/index/payment/ef_goods_return';//正式回调地址
//                    $notify_url = 'http://hongyun.twen.ltd/index/payment/ef_goods_return';//测试回调地址
                //之前的快捷支付是易票联的，现在改为了金运通的快捷支付 2023-12-06
//                    $efalipay = new Efalipay();
//                    $str = $efalipay->protocolPayPreRequest($order_sn,$card['protocol'],$amount,'购物',$notify_url,[]);
//                    if($str && $str[0] == 200){
//                        $res = json_decode($str[1],true);
//                        if($res['returnCode'] == 0000){
//                            Db::commit();
//                            $this->response($res,true);
//                        }
//                        $this->response($res['returnMsg']);
//                    }else{
//                        $this->response('失败请稍后再试');
//                    }
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
                        break;
                case 9:
                    $order_sn = 'GW'.self::randCreateOrderSn($user_id);
                    Db::name('mall_order')->where(['order_sn'=>$orderInfo['order_sn']])->update([
                        'order_sn'=>$order_sn
                    ]);

                    $notify_url = 'https://shop.gxqhydf520.com/index/payment/ef_goods_return';//正式回调地址
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
                    break;
                case 10://条码支付
                    $payChannel = input('payChannel');
                    $payMode = input('payMode');
                    $notify_url = 'https://shop.gxqhydf520.com/index/payment/jyt_goods_return';//正式回调地址
//                    $notify_url = 'https://hongyun.cqxjr.cn/index/payment/jyt_goods_return';//测试回调地址
                    $str = Payment::jyt_pay($payChannel,$payMode,$order_sn,$amount,'购物',$notify_url,get_client_ip());
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 11://微信支付-通道1  三方扫码支付
                    $str = Payment::Yy_pay($amount,$order_sn,'购物','',get_client_ip());
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 12://杉德支付 跳转三方链接
                    $str = (new Payment())->dopayment($order_sn,$amount,'购物',$this->member_id,$userInfo['nickname'],get_client_ip());
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 13://杉德支付 扫码支付
                    $str = (new Payment())->dopayment_qrcode($order_sn,$amount,'购物',$this->member_id,$userInfo['nickname'],get_client_ip());
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 14://杉德收银台
                    $str = (new Payment())->dopayment_h5($order_sn,$amount,'购物',$this->member_id,$userInfo['nickname'],get_client_ip());
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 15://盛付通-微信
                    $str = (new Payment())->shengPay($amount,$order_sn,'购物','购物',get_client_ip());
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 16://盛付通-支付宝
                    $str = (new Payment())->shengPay2($amount,$order_sn,'购物','购物',get_client_ip());
                    Db::commit();
                    $this->response($str,true);
                    break;
                default:
                    $this->response('支付方式有误！');
                    break;
            }

        }
    }

    //金运通银行卡确认支付
    public function jyt_card_pay_sure(){
        $order_sn = input('post.order_sn');
        $id = input('card_id');
        $verifyCode = input('verifyCode');
        $a = mb_substr($order_sn,0,2);
        if($a == 'JF'){//积分订单
            $orderInfo = Db::name('prestore_recharge')->lock(true)->where(['order_sn' => $order_sn])->find();
            if (!$orderInfo) {
                $this->response("订单编号({$order_sn})的订单不存在!");
            }

            if ($orderInfo['status'] > 0) {
                //支付后还在发送请求通知 给出success 防止支付方一直发请求
                $this->response("该订单已经付款");
            }
            $amount =$orderInfo['amount'];
        }
        if($a == 'GW'){//购物订单
            $orderInfo = Db::name('mall_order')->lock(true)->where(['order_sn' => $order_sn])->find();
            if (!$orderInfo) {
                $this->response("订单编号({$order_sn})的订单不存在!");
            }
            if (!$orderInfo or $orderInfo['order_status'] < 0) {
                $this->response("订单编号({$order_sn})的订单不存在或已失效!");
            }
            if ($orderInfo['order_status'] > 0) {
                //支付后还在发送请求通知 给出success 防止支付方一直发请求
                $this->response("该订单已经付款");
            }
            $amount =$orderInfo['order_amount'];
        }
        $card = Db::name('member_efalipay')->where(['id'=>$id,'member_id'=>$this->member_id,'status'=>1])->find();
        if(!$card){
            $this->response('没有此银行卡信息');
        }
        $str = Payment::jyt_card_pay_do($this->member_id,$order_sn,$card['bank_code'],$card['card_id'],$card['mobile'],$card['name'],$amount,$verifyCode);
        if($str){
            $res = json_decode($str,true);
            if($res['head']['respCode'] == 'S0000000'){
                Db::commit();
                $this->response($res['head']['respDesc'],true);
            }
            $this->response($res['head']['respDesc']);
        }else{
            $this->response('失败请稍后再试');
        }
    }
    /**
     * 订单支付
     * @action
     */
    public function order_pay_all()
    {
        if (request()->isPost()){
            $this->member_oplimit();
            $order_sn = input('post.order_sn');
            $pay_pwd = input('post.pay_pwd');
            $pay_type = input('post.pay_type');
            if(in_array($pay_type,[1,2])){
                if(!$this->userinfo['pay_pwd']){
                    $this->response('您还未设置支付密码',false,-4);
                }
                if(!preg_match('/^\d{6}$/',$pay_pwd)){
                    $this->response('支付密码有误');
                }
                if(!$this->checkPaypwd($pay_pwd)){
                    $this->response('支付密码有误');
                }
            }
            $orderInfo = Db::name('mall_order_all')->lock(true)->where(['order_sn' => $order_sn])->find();
            if (!$orderInfo) {
                $this->response("订单编号({$order_sn})的订单不存在!");
            }
            if (!$orderInfo or $orderInfo['order_status'] < 0) {
                $this->response("订单编号({$order_sn})的订单不存在或已失效!");
            }
            if ($orderInfo['order_status'] > 0) {
                //支付后还在发送请求通知 给出success 防止支付方一直发请求
                $this->response("该订单已经付款");
            }

            $user_id = $this->member_id;
            $userInfo = Db::name('member')->where(['id' => $user_id])->field('id,integral,points,green_points,lot,freeze_lot,nickname')->find();
            $amount =$orderInfo['order_amount'];
            Db::startTrans();
            switch ($pay_type){
                case 1://消费积分支付
                    if ($userInfo['integral'] < $amount) {
                        $this->response('您的消费积分余额不足!');
                    }
                    try {
                        Db::name('member')->where(['id' => $orderInfo['member_id']])->update([
                            'integral' => Db::raw('integral-' . $amount),
                        ]);
                        //添加余额记录
                        Db::name('bal_record')->insert([
                            'number' => -1 * $amount,
                            'type' => 10,
                            'info' => '商城购物消耗',
                            'member_id' => $orderInfo['member_id']
                        ]);
                        //更改订单状态
                        Db::name('mall_order_all')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                            'order_status' => 1,
                            'amount' => $amount,
                            'pay_time' => date('Y-m-d H:i:s'),
                            'pay_type' => $pay_type,
                        ]);
                        $order = Db::name('mall_order')->field('order_id,order_amount')->where(['order_status' => 0,'order_main' => $order_sn])->select();
                        foreach ($order as $value){
                            Db::name('mall_order')->where(['order_id' => $value['order_id']])->update([
                                'order_status' => 1,
                                'amount' => $value['order_amount'],
                                'pay_time' => date('Y-m-d H:i:s'),
                                'pay_type' => $pay_type,
                            ]);

                        }
                        //精选区购物返佣
                        if($orderInfo['goods_type'] == 2){
                            Push::main()->choice_rebate($amount,$orderInfo['member_id']);
                        }
                        //增加销量
                        $order_product = Db::name('mall_order')->where(['order_main' => $order_sn])->select();
                        foreach ($order_product as $v) {
                            Db::name('mall_product_spec')->where(['spec_id' => $v['spec_id']])->setInc('sales', $v['num']);
                            Db::name('mall_product')->where(['id' => $v['goods_id']])->setInc('sales', $v['num']);
                        }
                        Db::commit();
                        $this->response([
                            'order_sn'=>$orderInfo['order_sn'],
                            'msg'=>'支付成功'
                        ],true);
                    }catch (Exception $exception){
                        Db::rollback();
                        self::writeLog(21,'订单'.$order_sn.'积分支付失败',$exception->getMessage());
                        $this->response('支付失败，请稍后再试');
                    }

                    break;
                case 2://福分支付
                    if ($userInfo['lot'] < $amount) {
                        $this->response('您的消费积分余额不足!');
                    }
                    try {
                        Db::name('member')->where(['id' => $orderInfo['member_id']])->update([
                            'lot'=>Db::raw('lot-'.$amount),
                        ]);
                        //添加余额记录
                        Db::name('bal_record')->insert([
                            'number'=>-1*$amount,
                            'type'=>11,
                            'info'=>'商城购物消耗',
                            'member_id'=>$orderInfo['member_id']
                        ]);
                        //更改订单状态
                        Db::name('mall_order_all')->where(['order_status' => 0, 'order_sn' => $order_sn])->update([
                            'order_status' => 1,
                            'amount' => $amount,
                            'pay_time' => date('Y-m-d H:i:s'),
                            'pay_type' => $pay_type,
                        ]);
                        $order = Db::name('mall_order')->field('order_id,order_amount')->where(['order_status' => 0, 'order_main' => $order_sn])->select();
                        foreach ($order as $value){
                            Db::name('mall_order')->where(['order_id' => $value['order_id']])->update([
                                'order_status' => 1,
                                'amount' => $value['order_amount'],
                                'pay_time' => date('Y-m-d H:i:s'),
                                'pay_type' => $pay_type,
                            ]);

                        }
                        //精选区购物返佣
                        if($orderInfo['goods_type'] == 2){
                            Push::main()->choice_rebate($amount,$orderInfo['member_id']);
                        }
                        //增加销量
                        $order_product = Db::name('mall_order')->where(['order_main' => $order_sn])->select();
                        foreach ($order_product as $v) {
                            Db::name('mall_product_spec')->where(['spec_id' => $v['spec_id']])->setInc('sales', $v['num']);
                            Db::name('mall_product')->where(['id' => $v['goods_id']])->setInc('sales', $v['num']);
                        }
                        Db::commit();
                        $this->response([
                            'order_sn'=>$orderInfo['order_sn'],
                            'msg'=>'支付成功'
                        ],true);
                    }catch (Exception $exception){
                        Db::rollback();
                        self::writeLog(22,'订单'.$order_sn.'福分支付失败',$exception->getMessage());
                        $this->response('支付失败，请稍后再试');
                    }
                    break;
                case 3://微信支付
//                    $notify_url = 'https://suwen.dhlshu.cn/payment/renzhengHuidiao';   //自定义回调地址
                    $notify_url = 'https://shop.gxqhydf520.com/index/payment/wx_goods_return_all';   //测试回调地址
                    $str = Payment::openCloudWeixin($orderInfo['order_sn'],$amount,$notify_url);
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 4://支付宝支付
                    //        $notify_url = 'http://xintan.swkj2014.com/index/payment/alipay_callback';//正式回调地址
                    $notify_url = 'https://shop.gxqhydf520.com/index/payment/alipay_goods_return_all';//测试回调地址
                    $str = Payment::alipay_param($amount,$orderInfo['order_sn'],'商城购物订单：HY'.$randomNumber = mt_rand(17777777, 77777777),'商品',$notify_url);
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 7://易票联支付
                    $notify_url = 'https://shop.gxqhydf520.com/index/payment/ef_goods_return_all';   //正式回调地址
//                    $notify_url = 'http://hongyun.twen.ltd/index/payment/ef_goods_return_all';   //测试回调地址
                    $efalipay = new Efalipay();
                    $str = $efalipay->payPal($orderInfo['order_sn'],$amount,$notify_url);
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 8:
                    $id = input('card_id');
                    $card = Db::name('member_efalipay')->where(['id'=>$id,'member_id'=>$this->member_id,'status'=>1])->find();
                    if(!$card){
                        $this->response('没有此银行卡信息');
                    }
//                    $notify_url = 'https://shop.gxqhydf520.com/index/payment/ef_goods_return_all';//正式回调地址
////                    $notify_url = 'http://hongyun.twen.ltd/index/payment/ef_goods_return_all';//测试回调地址
//                    $efalipay = new Efalipay();
//                    $str = $efalipay->protocolPayPreRequest($order_sn,$card['protocol'],$amount,'消费积分充值',$notify_url,[]);
//                    if($str && $str[0] == 200){
//                        $res = json_decode($str[1],true);
//                        if($res['returnCode'] == 0000){
//                            Db::commit();
//                            $this->response($res,true);
//                        }
//                        $this->response($res['returnMsg']);
//                    }else{
//                        $this->response('失败请稍后再试');
//                    }
//                    break;
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
                    break;
                case 9:
                    $order_sn = 'GW'.self::randCreateOrderSn($user_id);
                    Db::name('mall_order')->where(['order_sn'=>$orderInfo['order_sn']])->update([
                        'order_sn'=>$order_sn
                    ]);

                    $notify_url = 'https://shop.gxqhydf520.com/index/payment/ef_goods_return';//正式回调地址
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
                    break;
                case 10://条码支付
                    $payChannel = input('payChannel');
                    $payMode = input('payMode');
                    $notify_url = 'https://shop.gxqhydf520.com/index/payment/jyt_goods_return_all';//正式回调地址
//                    $notify_url = 'https://hongyun.cqxjr.cn/index/payment/jyt_goods_return_all';//测试回调地址
                    $str = Payment::jyt_pay($payChannel,$payMode,$order_sn,$amount,'购物',$notify_url,get_client_ip());
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 11://微信支付-通道1  三方扫码支付
                    $str = Payment::Yy_pay($amount,$order_sn,'购物','',get_client_ip());
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 12://杉德支付 跳转三方链接
                    $str = (new Payment())->dopayment($order_sn,$amount,'购物',$this->member_id,$userInfo['nickname'],get_client_ip());
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 13://杉德支付 扫码支付
                    $str = (new Payment())->dopayment_qrcode($order_sn,$amount,'购物',$this->member_id,$userInfo['nickname'],get_client_ip());
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 14://杉德支付 收银台
                    $str = (new Payment())->dopayment_h5($order_sn,$amount,'购物',$this->member_id);
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 15://盛付通-微信
                    $str = (new Payment())->shengPay($amount,$order_sn,'购物','购物',get_client_ip());
                    Db::commit();
                    $this->response($str,true);
                    break;
                case 16://盛付通-支付宝
                    $str = (new Payment())->shengPay2($amount,$order_sn,'购物','购物',get_client_ip());
                    Db::commit();
                    $this->response($str,true);
                    break;
                default:
                    $this->response('支付方式有误！');
                    break;
            }

        }
    }
    /**
     * @return void 查询快递信息
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    /* 
    public function kuaidi(){
        $order_sn = input('get.order_sn');
        $num = input('get.courier_number');
        if(!$num || !$order_sn){
            $this->response('参数错误');
        }
        $where = "order_sn='".$order_sn."'";
        $where .= ' and member_id='.$this->member_id;
        $info = Db::name('mall_order')->field('order_id,order_status,order_amount,send_time,tran_type,goods_id,tel')->where($where)->find();
        if (!$info){
            $this->response('没有相关信息');
        }
        $info['goods'] = Db::name('mall_product')->where(['id'=>$info['goods_id']])->field('title,imglogo')->find();
        $key = 'wAdjoRUI6314';                        //客户授权key
        $customer = 'D3607B11207C77AD4FDE9CF4062D8BA4';                   //查询公司编号
        $phone = $info['tel'];
        $param = array (
            'com' => config('kuaidi')[$info['tran_type']],             //快递公司编码
            'phone' => $phone,
            'num' => $num    //快递单号
        );
        //请求参数
        $post_data = array();
        $post_data["customer"] = $customer;
        $post_data["param"] = json_encode($param);
        $sign = md5($post_data["param"].$key.$post_data["customer"]);
        $post_data["sign"] = strtoupper($sign);
        $url = 'http://poll.kuaidi100.com/poll/query.do';    //实时查询请求地址
        $params = "";
        foreach ($post_data as $k=>$v) {
            $params .= "$k=".urlencode($v)."&";              //默认UTF-8编码格式
        }
        $post_data = substr($params, 0, -1);
        //发送post请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        $data = json_decode($result,true);
        if($data['message'] == 'ok'){
            $data['kuaidi_name'] = $info['tran_type'];
            $this->response([
                'goods_info'=>$info,
                'logistics'=>$data
            ],true);
        }
        elseif (config('kuaidi')[$info['tran_type']] == '自提') {
           $data = [  
                'nu' => $num,  
                'kuaidi_name' => config('kuaidi')[$info['tran_type']],  
                'data' => [  
                    [  
                        "time" => "",  
                        "ftime" => "",  
                        "context" => "订单已完成 感谢您的支持！如有问题请联系商家。"  
                    ]
                ]  
            ];
            $this->response([
                'goods_info'=>$info,
                'logistics'=>$data
            ],true);
        }
        else{
            $data['status'] = 500;
            $data['nu'] = $num;
            $data['kuaidi_name'] = $info['tran_type'];
            $this->response([
                'goods_info'=>$info,
                'logistics'=>$data
            ],true);
        }

    }
    */
    private function fileterInput($value)
    {
        $value = trim($value);
        // 1. 去除 SQL 关键字
        $sql_keywords = [
            'select', 'insert', 'update', 'delete', 'drop', 'create', 'alter', 'truncate',
            'union', 'show', 'declare', 'exec', 'or', 'and', 'like', 'into', 'load', 'outfile',
            'table', 'database', 'case', 'when', 'then', 'group', 'by', 'having', 'limit', 'order',
            'join', 'on', 'inner', 'left', 'right', 'outer', 'cross', 'distinct', 'set', 'null'
        ];
        $input_lower = strtolower($value);

        foreach ($sql_keywords as $keyword) {
            $input_lower = str_replace($keyword, '', $input_lower);
        }

        // 3. 删除所有可能的恶意字符（如单引号、双引号、分号、注释符号等）
        $input_cleaned = preg_replace('/[\'"%;#()^&<>*\/]/', '', $input_lower);

        // 4. 删除 SQL 注释符号（-- 或 /* */）
        $input_cleaned = preg_replace('/(--|\/*\*.*?\*\/)/', '', $input_cleaned);

        // 5. 删除 SQL 函数 (例如: md5())
        // 这里通过更强的正则过滤掉括号中的函数（md5(1)）和类似的内容
        $input_cleaned = preg_replace('/\(\w+\s*\(\d+\)\)/', '', $input_cleaned); // 移除类似 md5(1)

        $input_cleaned = str_replace(',', '', $input_cleaned);

        // 7. 去除多余的符号和空格，特别是多余的斜杠和破坏性符号
        $input_cleaned = preg_replace('/[\/\-\_\*]+/', '', $input_cleaned);  // 去除多个破坏性符号
        $input_cleaned = preg_replace('/\s+/', '', $input_cleaned);  // 去除多余的空格
        return $input_cleaned;
    }
    public function kuaidi(){
        $order_sn = input('get.order_sn');
        $order_sn = $this->fileterInput($order_sn);
        $num = input('get.courier_number');
        //$num = intval($num);
        if(!$num || !$order_sn){
            $this->response('参数错误');
        }
//        $where = "order_sn='".$order_sn."'";
//        $where .= ' and member_id='.$this->member_id;
        $where = [
            'order_sn' => $order_sn,
            'member_id' => $this->member_id
        ];
        $info = Db::name('mall_order')->field('order_id,order_status,order_amount,send_time,tran_type,goods_id,tel')->where($where)->find();
        if (!$info){
            $this->response('没有相关信息');
        }
        $info['goods'] = Db::name('mall_product')->where(['id'=>$info['goods_id']])->field('title,imglogo')->find();
        $key = 'wAdjoRUI6314';                        //客户授权key
        $customer = 'D3607B11207C77AD4FDE9CF4062D8BA4';                   //查询公司编号
        $phone = $info['tel'];
        $param = array (
            'com' => config('kuaidi')[$info['tran_type']],             //快递公司编码
            'phone' => $phone,
            'num' => $num    //快递单号
        );
        //请求参数
        $post_data = array();
        $post_data["customer"] = $customer;
        $post_data["param"] = json_encode($param);
        $sign = md5($post_data["param"].$key.$post_data["customer"]);
        $post_data["sign"] = strtoupper($sign);
        $url = 'http://poll.kuaidi100.com/poll/query.do';    //实时查询请求地址
        $params = "";
        foreach ($post_data as $k=>$v) {
            $params .= "$k=".urlencode($v)."&";              //默认UTF-8编码格式
        }
        $post_data = substr($params, 0, -1);
        //发送post请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        $data = json_decode($result,true);
        if($data['message'] == 'ok'){
            $data['kuaidi_name'] = $info['tran_type'];
            $this->response([
                'goods_info'=>$info,
                'logistics'=>$data
            ],true);
        }
        elseif (config('kuaidi')[$info['tran_type']] == '自提') {
           $data = [  
                'nu' => $num,  
                'kuaidi_name' => config('kuaidi')[$info['tran_type']],  
                'data' => [  
                    [  
                        "time" => "",  
                        "ftime" => "",  
                        "context" => "订单已完成 感谢您的支持！如有问题请联系商家。"  
                    ]
                ]  
            ];
            $this->response([
                'goods_info'=>$info,
                'logistics'=>$data
            ],true);
        }
        else{
            $data['status'] = 500;
            $data['nu'] = $num;
            $data['kuaidi_name'] = $info['tran_type'];
            $this->response([
                'goods_info'=>$info,
                'logistics'=>$data
            ],true);
        }

    }
    
}
