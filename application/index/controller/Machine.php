<?php


namespace app\index\controller;


use think\Db;
use think\Exception;

class Machine extends Base
{
    protected $totalPrice = 0.00;//订单总价

    /**
     * 顺顺福可预约列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function index(){
        $thisTime = date('Y-m-d 00:00:00');
        $newTime = date('Y-m-d H:i:s');
        $ads = Db::name('ads')->cache(60)->field('imgurl,link,link_type')->where(['type'=>7,'status'=>1])->find();
        $list = Db::name('machine')->field('id,mname,thumb,price,start_time,end_time')->where("status=1 and end_time>'$thisTime'")->select();
        //已预约的顺顺福ID
        $machine_id = Db::name('machine_order')->field('machine_id')->where("member_id = $this->member_id and create_time>'".date('Y-m-d 00:00:00')."'")->column('machine_id');
        if($list){
            foreach ($list as&$value){
                $value['is_show'] = 1;
                $value['is_reservation'] = 0;
                if($value['end_time']<$newTime){
                    $value['is_show'] = 0;
                }
                if (in_array($value['id'],$machine_id)){
                    $value['is_reservation'] = 1;
                }

            }
        }
        $this->response([
            'list'=>$list,
            'ads'=>$ads,
            'is_click'=>$this->userinfo['is_click']
        ],true);
    }

    /**
     * 顺顺福预约记录
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function machine_order(){
        $page = input('get.page',1,'intval');
        $limit = 20;
        $where = [];
        $where[] = ['member_id','eq',$this->member_id];
        $list =  Db::name('machine_order o')->field('o.price,o.status,o.pay_time,m.mname')->leftJoin("machine m","m.id = o.machine_id")->where($where)->order('o.id desc')->limit(($page-1)*$limit,$limit)->select();
        $total = Db::name('machine_order')->where($where)->count('id');
        $this->response([
            'totalPage' => ceil($total/$limit),
            'list' => $list
        ],true);
    }
    /**
     * 顺顺福预约
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function machine_pay(){
        if(request()->isPost()){
            $this->member_oplimit();
            $tab = input('post.type',0);//1为一键预约 不传表示单个预约
            $id  = input('post.id',0);
            $day = date('Y-m-d 00:00:00');
            $thisTime = date('Y-m-d H:i:s');
            if(!$this->userinfo['is_vip']){
                $this->response('请购买新人福包，升级为正式会员',false,299);
            }
            //查询当天已预约的顺顺福ID
            $machine_id = Db::name('machine_order')->field('machine_id')->where("member_id = $this->member_id and create_time>'".date('Y-m-d 00:00:00')."'")->column('machine_id');
            
// 日志
/*
list($usec, $sec) = explode(' ', microtime());  
$msec = round($usec * 1000); // 将微秒转换为毫秒并四舍五入  
$timestamp = date('Y-m-d H:i:s') . '.' . str_pad($msec, 3, '0', STR_PAD_LEFT); 
$logMessage = '是否一键预约：'.$tab." id:". $this->member_id.' 时间：'. $timestamp.' thisTime:'.$thisTime;  
self::writeLog(33,$logMessage,'预约日志');
*/
// 日志
            
            if($tab){
                if($this->userinfo['is_click']){
                    $this->response('一键预约成功',true);
                }else{
                    Db::name('member')->where(['id'=>$this->member_id])->update([
                        'is_click'=>1
                    ]);
                    $this->response('一键预约成功',true);
                }
            }else{
                if(!$id){
                    $this->response('您预约的顺顺福不存在或已失效！请确认！');
                }
                if(in_array($id,$machine_id)){
                    $this->response('该顺顺福您已经预约过了，无需再次预约！');
                }
                
                /*修复双倍预约的问题*/
                $member_infox = Db::name('member')->field('is_click')->where(['id'=>$this->member_id])->find();
                if($member_infox['is_click'] == 1){
                    $this->response('您已经一键预约成功，无需手动预约！');
                }
                /*修复双倍预约的问题*/
                
                $list = Db::name('machine')->field('id,price,income')->where("id = $id and status=1 and start_time<'$thisTime' and end_time>'$thisTime'")->select();
            }
            if(!$list){
                $this->response('该顺顺福不在预约时间内！');
            }
            $orderArr  = [];
            $count = Db::name('machine')->where("start_time>'$day' and end_time<'$thisTime'")->count('id');
            foreach ($list as $item){
                if (in_array($item['id'],$machine_id)){//一键预约时，跳过已预约的单子
                    continue;
                }
                $this->totalPrice+=$item['price'];//计算总金额
                $orderArr[] = [
                    'machine_id'=>$item['id'],
                    'member_id'=>$this->member_id,
                    'price'=>$item['price'],
                    'income'=>$item['income'],
                    'pay_time'=>date('Y-m-d H:i:s'),
                    'order_sn'=>self::crate_rand_str(5).$this->member_id.time()
                ];
            }
            if(!$this->totalPrice){//总价为0表示已经全部预约
                $this->response('没有可以预约的顺顺福！');
            }
            $integral = Db::name('member')->where(['id'=>$this->member_id])->value('integral');
            if($integral<$this->totalPrice){
                $this->response('您的消费积分余额不足!');
            }
            Db::startTrans();
            try {
                Db::name('member')->where(['id'=>$this->member_id])->update([
                    'integral' => Db::raw('integral-' . $this->totalPrice),
                    'click_num' => ($count+1),
                ]);
                //写入记录
                Db::name('bal_record')->insert([
                    'number'=>-1*$this->totalPrice,
                    'type'=>18,
                    'info'=>'顺顺福预约消耗',
                    'member_id'=>$this->member_id
                ]);
                Db::name('machine_order')->insertAll($orderArr);
                //增加参与记录
                if(date('Y-m-d')>$this->userinfo['update_time']){
                    Db::name('member')->where(['id'=>$this->member_id])->update([
                        'day'=>Db::raw('day+1'),
                        'update_time'=>date('Y-m-d')
                    ]);
                }
                $shipNum = Db::name('machine_order')->where("member_id = $this->member_id and create_time>'".date('Y-m-d 00:00:00')."'")->count('id');
                if($shipNum>=10){
                    Db::name('member')->where(['id'=>$this->member_id])->update([
                        'is_ship'=>1
                    ]);
                }
                Db::commit();
                $this->response('恭喜您预约成功！本次消耗'.$this->totalPrice.'消费积分！',true);
            }catch (Exception $exception){
                Db::rollback();
                $this->response('预约失败，请稍后再试！'.$exception->getMessage());
            }

        }
    }
}