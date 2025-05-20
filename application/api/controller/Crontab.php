<?php
namespace app\api\controller;
use app\index\controller\Push;
use app\lib\AliSms;
use function Sodium\add;
use think\Db;
use think\Exception;

class Crontab extends Base
{
    /*
     * 一分钟执行一次
     */
    public function oneMinOneTimes(){
        
        $time_log  = date('Y-m-d H:i:s');
        $start_time_x = microtime(true);
        
        $this->machine_refund();//顺顺福退款
        $this->clear_order();//清理长时间未支付顶单
        $this->complete_order();//超过7天自动收货
        $this->expire_coupon();//处理优惠券过期
        
        
        $end_time_x = microtime(true);
        $execution_time_x = sprintf('%.2f seconds', $end_time_x - $start_time_x);
        Db::name('log')->insert([
                    'level'=>'oneMinOneTimes',
                    'type'=>74,
                    'msg'=>$execution_time_x.' | 始于:'.$time_log,
                    'create_time'=>date('Y-m-d H:i:s')
        ]);
        
    }

    /**
     * 处理优惠券过期
     * @return void
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    private function expire_coupon(){
        Db::name('coupon_pick')->where("status = 1 and end_time<'".date('Y-m-d')."'")->update([
            'status'=>3
        ]);
        Db::name('coupon_list')->where("status = 1 and end_time<'".date('Y-m-d')."'")->update([
            'status'=>3
        ]);
    }
    /**
     * 7天后自动收货
     * @return false|void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    private function complete_order(){
        $day = self::$config['mall_day'];
        $the_goods_sec = 84000*$day;
        $time = time()-$the_goods_sec;
        $the_goods_list = Db::name('mall_order')->field('order_id,goods_type,amount,order_bal_amount,member_id,pay_type')->where("is_off = 0 and order_status=2 and UNIX_TIMESTAMP(send_time)<={$time}")->limit(500)->select();
        if (!$the_goods_list){
          return false;
        }
        $order_id_arr = array();
        foreach ($the_goods_list as $v) {
            $order_id_arr[] = $v['order_id'];
            $total_amount = $v['amount']+$v['order_bal_amount'];
            //给城市代理和驿站代理反佣
            if($v['pay_type'] != 6){
                if($v['goods_type'] == 1){}else{//普通购物不返佣
                Push::main()->city_commission($v['member_id'],$total_amount);
                }
            }
            //增加业绩
            Push::main()->get_merits($v['member_id'],$total_amount);
            //会员区购物返佣
            if($v['goods_type'] == 5 || $v['goods_type'] == 7){
                Push::main()->members_rebate($v['member_id']);
            }
            //会员区购物返佣    十二生肖也返
            if($v['goods_type'] == 1 || $v['goods_type'] == 8){
                Push::main()->common_rebate($total_amount,$v['member_id']);
            }
            
            if($v['goods_type'] == 8){
                //十二生肖返佣自己
                 Push::main()->bronYearToSelf($total_amount,$v['member_id'],$v['goods_id']);
                 //十二生肖返佣上级
                 Push::main()->bronYear($total_amount,$v['member_id']);
            }
        }
        $res = Db::name('mall_order')->where([['order_id','in', $order_id_arr]])->update([
            'order_status' => 3,
            'over_time' => date('Y-m-d H:i:s')
        ]);
        echo "auto goods: {$res}\n";
    }

    /**
     * 清理未支付订单
     * @return false|void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    private function clear_order()
    {
        $order_goods_failure = 7200;
        $orderList = Db::name('mall_order')->where("order_status=0 and (unix_timestamp(`add_time`)+{$order_goods_failure})<unix_timestamp()")->limit(500)->select();
        if(!count($orderList)){
            return false;
        }
        $result = [];
        foreach ($orderList as $k => $v) {
            $result[$k]['order'] = Db::name('mall_order')->where(['order_status' => 0, 'order_id' => $v['order_id']])->update(['order_status' => -1]);
            if ($result[$k]['order']) {
                    //收回库存
                    $result[$k]['goods_stock'][] = Db::name('mall_product')->where(['id' => $v['goods_id']])->update([
                        'stock' => Db::raw('stock+' . $v['num'])
                    ]);
                    //$result[$k]['spec_stock'][] = Db::name('mall_product_spec')->where(['product_id' => $v['goods_id']])->update([
                    $result[$k]['spec_stock'][] = Db::name('mall_product_spec')->where(['spec_id' => $v['spec_id']])->update([
                        'stock' => Db::raw('stock+' . $v['num'])
                    ]);
            }
        }
        echo "clear overdue goods order: " . json_encode($result, 256) . "\n";
    }
    /**
     * 提货券每日收益
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function machine_income(){
        
        //
        // 获取当前时间
        $currentTime = date('H:i');
        // 定义开始时间和结束时间
        $startTime = '00:00';
        $endTime = '03:00';
      
        // 检查当前时间是否在指定范围内
        if (!($startTime <= $currentTime && $currentTime < $endTime)) {
            // 如果不在指定时间范围内，则直接返回
            echo '不在执行时间范围内(00:00-03:00)-跳过';
            return;
        }
        
        Db::name('log')->insert([
                    'level'=>'machine_income',
                    'type'=>98,
                    'msg'=>$currentTime,
                    'create_time'=>date('Y-m-d H:i:s')
        ]);
        //
        
        $time  = date('Y-m-d 00:00:00');
        $time_log  = date('Y-m-d H:i:s');
        $start_time_x = microtime(true);
        $data = Db::name('machine_pick')->field('id,member_id,lot,freeze_lot,total_freeze,income_times,ex_time')->where([['status','eq',1],['is_lock','eq',0],['machine_time','lt',$time]])->limit(5000)->select(); //7000 
        if(!$data){
            exit('no order');
        }
        foreach ($data as $item){
            if($item['income_times']>=$item['ex_time']){
                Db::name('machine_pick')->where(['id'=>$item['id']])->update([
                    'is_lock'=>1,
                    'status'=>3,
                    'is_res'=>1,
                    'cancel_time' => date('Y-m-d H:i:s')
                ]);
                //将对应冻结福分解冻
                $memberFreeze = Db::name('member')->where(['id'=>$item['member_id']])->value('freeze_lot');
                if(!$memberFreeze){
                    continue;
                }
                $freezeNum = $item['total_freeze']>$memberFreeze?$memberFreeze:$item['total_freeze'];
                $cutNum = round($freezeNum*0.9,2);
                //写入记录
                Db::name('bal_record')->insert([
                    'number'=>-1*$freezeNum,
                    'type'=>14,
                    'info'=>'转换为消费积分',
                    'member_id'=>$item['member_id']
                ]);
                Db::name('bal_record')->insert([
                    'number'=>$cutNum,
                    'type'=>15,
                    'info'=>'冻结福分转换为消费积分',
                    'member_id'=>$item['member_id']
                ]);
                Db::name('member')->where(['id'=>$item['member_id']])->update([
                    'integral' => Db::raw('integral+'.$cutNum),
                    'freeze_lot' => Db::raw('freeze_lot-'.$freezeNum),
                ]);
                //如果有矿机过期 则给开启一台没有运行的
                Db::name('machine_pick')->where(['member_id'=>$item['member_id'],'status'=>1,'is_lock'=>2])->limit(1)->update([
                    'is_lock'=>0
                ]);
                continue;
            }
            $memGreen = Db::name('member')->where(['id'=>$item['member_id']])->value('green_points');
            $lot = $item['lot'];
            $freeze_lot = $item['freeze_lot'];
//            if($item['income_times']>50){
//                $lot = $item['lot']*2;
//                $freeze_lot = $item['freeze_lot']*2;
//            }
            //给上级返佣
            Push::main()->machine_lot($item['member_id']);
            //Push::main()->machine_lots($item['member_id']);
            //8-12代
            
            $total_lot = $lot+$freeze_lot;
            if($total_lot<=$memGreen){
                Db::name('member')->where(['id'=>$item['member_id']])->update([
                    'lot' => Db::raw('lot+'.$lot),
                    'green_points' => Db::raw('green_points-'.$total_lot),
                    'freeze_lot' => Db::raw('freeze_lot+'.$freeze_lot),
                ]);
                Db::name('bal_record')->insert([
                    'type' => 34,
                    'member_id' => $item['member_id'],
                    'number' =>-1*$total_lot,
                    'info' => '提货券每日收益消耗-绿色积分',
                ]);
                Db::name('bal_record')->insert([
                    'type' => 27,
                    'member_id' => $item['member_id'],
                    'number' =>$lot,
                    'info' => '提货券每日收益-福分',
                ]);
                Db::name('bal_record')->insert([
                    'type' => 28,
                    'member_id' => $item['member_id'],
                    'number' =>$freeze_lot,
                    'info' => '提货券每日收益-冻结福分',
                ]);
            }else{
                $freeze_lot = 0;
                $lot = 0;
                //以上是BUG的补丁
                writeLog_s($item['member_id'],'绿色积分',$total_lot,'绿色积分不够,提货券每日收益无法发放');
            }
            if($item['income_times']+1>=$item['ex_time']){
                Db::name('machine_pick')->where(['id'=>$item['id']])->update([
                    'is_lock'=>1,
                    'status'=>3,
                    'is_res'=>1,
                    'cancel_time' => date('Y-m-d H:i:s')
                ]);
                //将对应冻结福分解冻
                $memberFreeze = Db::name('member')->where(['id'=>$item['member_id']])->value('freeze_lot');
                if(!$memberFreeze){
                    continue;
                }
                $freezeTotal = $item['total_freeze']+$freeze_lot;
                $freezeNum = $freezeTotal>$memberFreeze?$memberFreeze:$freezeTotal;
                $cutNum = round($freezeNum*0.9,2);
                //写入记录
                Db::name('bal_record')->insert([
                    'number'=>-1*$freezeNum,
                    'type'=>14,
                    'info'=>'转换为消费积分',
                    'member_id'=>$item['member_id']
                ]);
                Db::name('bal_record')->insert([
                    'number'=>$cutNum,
                    'type'=>15,
                    'info'=>'冻结福分转换为消费积分',
                    'member_id'=>$item['member_id']
                ]);
                Db::name('member')->where(['id'=>$item['member_id']])->update([
                    'integral' => Db::raw('integral+'.$cutNum),
                    'freeze_lot' => Db::raw('freeze_lot-'.$freezeNum),
                ]);
                //如果有矿机过期 则给开启一台没有运行的
                Db::name('machine_pick')->where(['member_id'=>$item['member_id'],'status'=>1,'is_lock'=>2])->limit(1)->update([
                    'is_lock'=>0
                ]);
            }

            Db::name('machine_pick')->where(['id'=>$item['id']])->update([
                'total_lot' => Db::raw('total_lot+'.$lot),
                'total_freeze' => Db::raw('total_freeze+'.$freeze_lot),
                'income_times' => Db::raw('income_times+1'),
                'machine_time' => date('Y-m-d H:i:s')
            ]);

        }
        
        $end_time_x = microtime(true);
        $execution_time_x = sprintf('%.2f seconds', $end_time_x - $start_time_x);
        Db::name('log')->insert([
                    'level'=>'plan_machine_income',
                    'type'=>74,
                    'msg'=>$execution_time_x.' | 始于:'.$time_log,
                    'create_time'=>date('Y-m-d H:i:s')
        ]);
        
    }

    /**
     * 平仓券每日收益
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function machine_close(){
        //
        // 获取当前时间
        $currentTime = date('H:i');
        // 定义开始时间和结束时间
        $startTime = '00:00';
        $endTime = '03:00';
      
        // 检查当前时间是否在指定范围内
        if (!($startTime <= $currentTime && $currentTime < $endTime)) {
            // 如果不在指定时间范围内，则直接返回
            echo '不在执行时间范围内(00:00-03:00)-跳过';
            return;
        }
        
        Db::name('log')->insert([
                    'level'=>'machine_close',
                    'type'=>98,
                    'msg'=>$currentTime,
                    'create_time'=>date('Y-m-d H:i:s')
        ]);
        //
        
        
        $time  = date('Y-m-d 00:00:00');
        $data = Db::name('machine_close')->field('id,member_id,income,income_times,ex_time')->where([['status','eq',1],['is_lock','eq',0],['machine_time','lt',$time]])->limit(3000)->select();
        if(!$data){
            exit('no order');
        }
        foreach ($data as $item){
            if($item['income_times']>=$item['ex_time']){
                Db::name('machine_close')->where(['id'=>$item['id']])->update([
                    'status'=>3,
                    'cancel_time' => date('Y-m-d H:i:s')
                ]);
                continue;
            }
            $income = $item['income'];
            Db::name('member')->where(['id'=>$item['member_id']])->setInc('integral',$income);
            Db::name('bal_record')->insert([
                'type' => 29,
                'member_id' => $item['member_id'],
                'number' =>$income,
                'info' => '平仓券每日收益',
            ]);
            if($item['income_times']+1>=$item['ex_time']){
                Db::name('machine_close')->where(['id'=>$item['id']])->update([
                    'status'=>3,
                    'cancel_time' => date('Y-m-d H:i:s')
                ]);
            }
            Db::name('machine_close')->where(['id'=>$item['id']])->update([
                'income_times' => Db::raw('income_times+1'),
                'machine_time' => date('Y-m-d H:i:s')
            ]);

        }
    }
    /**
     * 每日中单
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function machine_centre(){
        $time = date("Y-m-d");
        $is_exist = Db::name('machine_cut')->where([['create_time','gt',$time]])->count();
        if($is_exist){
            exit('今日已处理中单');
        }
        //清楚一键预约相关信息
        Db::name('member')->where("is_click = 1 or click_num>0")->update([
            'is_click'=>0,
            'click_num'=>0
        ]);
        //查询当天参加的用户ID
        $totalNum = Db::name('machine_order')->where("status = 1 and create_time>'".date('Y-m-d 00:00:00')."'")->count('id');
        if(!$totalNum){
            exit('还没有用户参加');
        }
        $num = ceil($totalNum*self::$config['machine_ratio']); //当日中单人数
        $todayMemberId = Db::name('machine_order')->where("status = 1 and create_time>'".date('Y-m-d 00:00:00')."'")->group('member_id')->column('member_id');  //当日参加的用户ID
        $cut_10 = Db::name('member')->field('id')->where([['id','in',$todayMemberId],['day','gt',10]])->select();//查询10天以上的用户
        if(count($cut_10) == 0){
            exit('无中单用户');
        }
        $cutMemberId = '';
        $cutMemberArr = [];
        foreach ($cut_10 as $item){
            $cutMemberId .= $item['id'].',';
            $cutMemberArr[] = $item['id'];
        }
        $idstr = substr($cutMemberId,0,-1);
        $centreId = Db::name('member')->field('id')->where("id in({$idstr}) and day>=60 and cut_num=0")->column('id');//筛选出60天中过单的id
        if($centreId){ //如果有必中ID
            $centreNum = count($centreId); //必中ID数量
            if($centreNum>=$num){
                $final_id = $centreId;//如果必中ID比中单数量多 直接得到今天中单ID
            }else{
                //必中ID比今天中单数少 再从10次后的用户抽出中单ID
                $finalNum = $num - $centreNum;
                $finalArr = array_diff($cutMemberArr,$centreId);
                $final = array_rand($finalArr,$finalNum);
                if($finalNum == 1){
                    $final_id = array_merge([$finalArr[$final]],$centreId);
                }else{
                    $final_id = array_merge($this->sui_ji($finalArr,$final),$centreId);
                }

            }
        }else{
            //如果没有必中ID 直接从10天内用户抽取中单ID
            $final= array_rand($cutMemberArr,$num);
            if($num == 1){
                $final_id = [$cutMemberArr[$final]];
            }else{
                $final_id = array_merge($this->sui_ji($cutMemberArr,$final),$centreId);
            }
        }
        if(count($final_id) == 0){
            exit('无中单ID');
        }

        Db::startTrans();
        try {
            $machinePick = [];
            foreach ($final_id as $value){
                $machine_order = Db::name('machine_order')->field('machine_id,order_sn,price,id')->where("member_id = $value and status = 1 and create_time>'".date('Y-m-d 00:00:00')."'")->orderRand()->find();
                $countPick = Db::name('machine_pick')->where([['status','eq',1],['is_lock','eq',0],['member_id','eq',$value]])->count();
                if($countPick<40){
                    $machinePick[] = [
                        'logo'=>self::$config['pick_img'],
                        'machine_id'=>$machine_order['machine_id'],
                        'member_id'=>$value,
                        'price'=>$machine_order['price'],
                        'lot'=>self::$config['lot'],
                        'freeze_lot'=>self::$config['freeze_lot'],
                        'status'=>1,
                        'is_lock'=>0,
                        'ex_time'=>100,
                        'active_time'=>date('Y-m-d H:i:s'),
                        'order_sn'=>$machine_order['order_sn'],
                        'machine_time'=>date('Y-m-d H:i:s')
                    ];
                }else{
                    $machinePick[] = [
                        'logo'=>self::$config['pick_img'],
                        'machine_id'=>$machine_order['machine_id'],
                        'member_id'=>$value,
                        'price'=>$machine_order['price'],
                        'lot'=>self::$config['lot'],
                        'freeze_lot'=>self::$config['freeze_lot'],
                        'status'=>1,
                        'is_lock'=>2,
                        'ex_time'=>100,
                        'active_time'=>date('Y-m-d H:i:s'),
                        'order_sn'=>$machine_order['order_sn'],
                        'machine_time'=>date('Y-m-d H:i:s')
                    ];
                }

                Db::name('machine_order')->where(['id'=>$machine_order['id']])->update([
                    'status'=>3,
                    'active_time'=>date('Y-m-d H:i:s')
                ]);
                Db::name('member')->where(['id'=>$value])->update([
                    'cut_num'=>Db::raw('cut_num+1'),
                    'last_cut_time'=>date('Y-m-d H:i:s')
                ]);
            }
            Db::name('machine_pick')->insertAll($machinePick);
            Db::name('machine_cut')->insert([
                'cut_id'=>json_encode($final_id)
            ]);
            Db::commit();
            echo '中单操作完成';
        }catch (Exception $exception){
            Db::rollback();
            self::writeLog(10,json_encode($final_id).'中单错误'.$exception->getMessage(),'中单错误');
        }



    }
    
    /**
     * 每日中单 手动处理
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function machine_centre_c1(){
        //$data = input('post.');
        $start_time = "2024-04-02 00:00:00";
        $end_time = "2024-04-02 23:59:59";
        $time = date('Y-m-d',strtotime($start_time));
        $is_exist = Db::name('machine_cut')->where([['create_time','gt',$time]])->count();
        if($is_exist){
            exit($start_time.':已处理中单');
        }
        //清楚一键预约相关信息
        Db::name('member')->where("is_click = 1 or click_num>0")->update([
            'is_click'=>0,
            'click_num'=>0
        ]);

        //查询当天参加的用户ID
        $totalNum = Db::name('machine_order')->where("status = 1 and create_time>'".$start_time."' and create_time<'".$end_time . "'")->count('id');
        if(!$totalNum){
            exit('还没有用户参加');
        }
        $num = ceil($totalNum*self::$config['machine_ratio']); //当日中单人数
        echo $totalNum.'|'.$num;
        $todayMemberId = Db::name('machine_order')->where("status = 1 and create_time>'".$start_time."' and create_time<'" . $end_time . "'")->group('member_id')->column('member_id');  //当日参加的用户ID
        $cut_10 = Db::name('member')->field('id')->where([['id','in',$todayMemberId],['day','gt',10]])->select();//查询10天以上的用户
        
        if ($num > count($cut_10)){
        //echo '计算出来的的中单数大于实际可以中单的人数';
        $num = count($cut_10);
        }
        
        if(count($cut_10) == 0){
            exit('无中单用户');
        }
        $cutMemberId = '';
        $cutMemberArr = [];
        foreach ($cut_10 as $item){
            $cutMemberId .= $item['id'].',';
            $cutMemberArr[] = $item['id'];
        }
        $idstr = substr($cutMemberId,0,-1);
        $centreId = Db::name('member')->field('id')->where("id in({$idstr}) and day>=60 and cut_num=0")->column('id');//筛选出60天中过单的id
        if($centreId){ //如果有必中ID
            $centreNum = count($centreId); //必中ID数量
            if($centreNum>=$num){
                $final_id = $centreId;//如果必中ID比中单数量多 直接得到今天中单ID
            }else{
                //必中ID比今天中单数少 再从10次后的用户抽出中单ID
                $finalNum = $num - $centreNum;
                $finalArr = array_diff($cutMemberArr,$centreId);
                $final = array_rand($finalArr,$finalNum);
                if($finalNum == 1){
                    $final_id = array_merge([$finalArr[$final]],$centreId);
                }else{
                    $final_id = array_merge($this->sui_ji($finalArr,$final),$centreId);
                }

            }
        }else{
            //如果没有必中ID 直接从10天内用户抽取中单ID
            $final= array_rand($cutMemberArr,$num);
            if($num == 1){
                $final_id = [$cutMemberArr[$final]];
            }else{
                $final_id = array_merge($this->sui_ji($cutMemberArr,$final),$centreId);
            }
        }
        if(count($final_id) == 0){
            exit('无中单ID');
        }
        Db::startTrans();
        try {
            $machinePick = [];
            foreach ($final_id as $value){
                $machine_order = Db::name('machine_order')->field('machine_id,order_sn,price,id')->where("member_id = $value and status = 1 and create_time>'".$start_time."' and create_time<'".$end_time."'")->orderRand()->find();
                $countPick = Db::name('machine_pick')->where([['status','eq',1],['is_lock','eq',0],['member_id','eq',$value]])->count();
                if($countPick<40){
                    $machinePick[] = [
                        'logo'=>self::$config['pick_img'],
                        'machine_id'=>$machine_order['machine_id'],
                        'member_id'=>$value,
                        'price'=>$machine_order['price'],
                        'lot'=>self::$config['lot'],
                        'freeze_lot'=>self::$config['freeze_lot'],
                        'status'=>1,
                        'is_lock'=>0,
                        'ex_time'=>100,
                        'active_time'=>$end_time,
                        'order_sn'=>$machine_order['order_sn'],
                        'machine_time'=>$end_time
                    ];
                }else{
                    $machinePick[] = [
                        'logo'=>self::$config['pick_img'],
                        'machine_id'=>$machine_order['machine_id'],
                        'member_id'=>$value,
                        'price'=>$machine_order['price'],
                        'lot'=>self::$config['lot'],
                        'freeze_lot'=>self::$config['freeze_lot'],
                        'status'=>1,
                        'is_lock'=>2,
                        'ex_time'=>100,
                        'active_time'=>$end_time,
                        'order_sn'=>$machine_order['order_sn'],
                        'machine_time'=>$end_time
                    ];
                }

                Db::name('machine_order')->where(['id'=>$machine_order['id']])->update([
                    'status'=>3,
                    'active_time'=>$end_time
                ]);
                Db::name('member')->where(['id'=>$value])->update([
                    'cut_num'=>Db::raw('cut_num+1'),
                    'last_cut_time'=>$end_time
                ]);
            }
            //未执行
            Db::name('machine_pick')->insertAll($machinePick);
            Db::name('machine_cut')->insert([
                'cut_id'=>json_encode($final_id),
                'create_time'=> $end_time
            ]);
            Db::commit();
            echo '中单操作完成';
            //未执行
        }catch (Exception $exception){
            Db::rollback();
            self::writeLog(10,json_encode($final_id).'中单错误'.$exception->getMessage(),'中单错误');
        }
    }

    static function sui_ji($arr,$keys){
        $re=array();
        foreach($keys as $v){
            $re[$v]=$arr[$v];
        }
        return $re;
    }
    
    /**
     * @return void 定时添加顺顺福预约单
     */
    public function add_machine(){
        $data = Db::name('machine')->where("create_time>'".date('Y-m-d 00:00:00')."'")->find();
        if ($data){
            exit('已创建');
        }
        $machine = Db::name('machine_init')->order('id asc')->select();
        $dataArr = [];
        $startTime = date('Y-m-d').' ';
        foreach ($machine as $item){
            $dataArr[] = [
                'mname'=>$item['mname'],
                'thumb'=>$item['thumb'],
                'price'=>$item['price'],
                'income'=>$item['income'],
                'status'=>$item['status'],
                'sort'=>$item['sort'],
                'start_time'=>$startTime.$item['start_time'],
                'end_time'=>$startTime.$item['end_time']
            ];
        }
        Db::name('machine')->insertAll($dataArr);
        echo '创建成功';
    }

    /**
     * 加权分红
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function weight(){
        $totalBal = Db::name('mall_order')->where("order_status in (1,2,3) and add_time>'".date('Y-m-d 00:00:00')."'")->sum('order_amount');
        if(!$totalBal){
            exit('无相关分红');
        }
        $data = Db::name('mall_weight')->where('status = 1')->select();
        if(!$data){
            exit('无相关设定');
        }
        foreach ($data as $item){
            $name  = $item['name'];
            $total = round($totalBal*$item['ratio']/100,2);

            $member_list = Db::name('mall_weight_list')->field('member_id')->where(['weight_id'=>$item['id']])->select();
            $memberNum = count($member_list);
            if(!$memberNum){
                continue;
            }
            $num = round($total/$memberNum,2);
            if($num<0.01){
                continue;
            }
            $dataArr  = [];
            foreach ($member_list as $member){
                $green_points = Db::name('member')->where(['id'=>$member['member_id']])->value('green_points');
                if($green_points<=0){
                    continue;
                }
                $number = $green_points>$num?$num:$green_points;
                Db::name('member')->where(['id'=>$member['member_id']])->update([
                    'green_points' => Db::raw('green_points-' . $number),
                    'lot' => Db::raw('lot+' . $number)
                ]);
                $dataArr[] = [ //福分赠送记录
                    'number'=>$number,
                    'type'=>35,
                    'info'=>'APP每日购物'.$name.'分红',
                    'member_id'=>$member['member_id']
                ];
                $dataArr[] = [ //绿色积分扣除记录
                    'number'=>-1*$number,
                    'type'=>36,
                    'info'=>'APP每日购物'.$name.'分红消耗',
                    'member_id'=>$member['member_id']
                ];
            }
            Db::name('bal_record')->insertAll($dataArr);
        }
    }
    public function click_appointment(){
        $time = date('Y-m-d H:i:s');
        $day = date('Y-m-d 00:00:00');
        //
        
        // 获取当前时间
        $currentTime = date('H:i');
        // 定义开始时间和结束时间
        $startTime = '09:00';
        $endTime = '20:00';
        // 检查当前时间是否在指定范围内
        if (!($startTime <= $currentTime && $currentTime < $endTime)) {
            // 如果不在指定时间范围内，则直接返回
            echo '不在执行时间范围内(09:00-20:00)-跳过';
            return;
        }
        Db::name('log')->insert([
                    'level'=>'click_appointment',
                    'type'=>98,
                    'msg'=>$currentTime,
                    'create_time'=>date('Y-m-d H:i:s')
        ]);
        
        //
        $machine = Db::name('machine')->where("start_time<'$time' and end_time>'$time'")->find();
        $count = Db::name('machine')->where("start_time>'$day' and end_time<'$time'")->count('id');
        if(!$machine){
            exit('no machine');
        }
        $member = Db::name('member')->field('id,integral,update_time,is_ship,click_num')->where("integral>=2000 and is_click=1 and click_num <= $count")->select();
        if(!$member){
            exit('no member');
        }
        foreach ($member as $item){
            $memberMachine = Db::name('machine_order')->where(['member_id'=>$item['id'],'machine_id'=>$machine['id']])->find();
            if($memberMachine){
                Db::name('member')->where(['id'=>$item['id']])->update([
                    'click_num' => ($count+1),
                ]);
                continue;
            }
            $integral = Db::name('member')->where(['id'=>$item['id']])->value('integral');
            if($integral <$machine['price']){
                continue;
            }
            Db::name('machine_order')->insert([
                'machine_id'=>$machine['id'],
                'member_id'=>$item['id'],
                'price'=>$machine['price'],
                'income'=>$machine['income'],
                'pay_time'=>date('Y-m-d H:i:s'),
                'order_sn'=>self::crate_rand_str(5).$item['id'].time()
            ]);
            Db::name('bal_record')->insert([
                'number'=>-1*$machine['price'],
                'type'=>18,
                'info'=>'顺顺福一键预约消耗',
                'member_id'=>$item['id']
            ]);
            Db::name('member')->where(['id'=>$item['id']])->update([
                'integral' => Db::raw('integral-' . $machine['price']),
                'click_num' => ($count+1),
            ]);
            //增加参与记录
            if(date('Y-m-d')>$item['update_time']){
                Db::name('member')->where(['id'=>$item['id']])->update([
                    'day'=>Db::raw('day+1'),
                    'update_time'=>date('Y-m-d')
                ]);
            }
            if($item['is_ship'] == 1){
                continue;
            }
            $shipNum = Db::name('machine_order')->where("member_id = {$item['id']} and create_time>'".date('Y-m-d 00:00:00')."'")->count('id');
            if($shipNum>=10){
                Db::name('member')->where(['id'=>$item['id']])->update([
                    'is_ship'=>1
                ]);
            }
        }
    }

    /**
     * 处理已使用提货券满100天后解冻冻结福分
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function machine_unlock(){
        $order = Db::name('machine_pick')->field('member_id,total_freeze,id')->where('active_time <DATE_SUB(NOW(),INTERVAL 100 day) and status = 2 and is_res = 0')->limit(3000)->select();
        if(!$order){
            exit('no order');
        }
        foreach ($order as $item){
            Db::name('machine_pick')->where(['id'=>$item['id']])->update([
                'is_res'=>1,
            ]);
            $memberFreeze = Db::name('member')->where(['id'=>$item['member_id']])->value('freeze_lot');
            if(!$memberFreeze){
                continue;
            }
            $freezeNum = $item['total_freeze']>$memberFreeze?$memberFreeze:$item['total_freeze'];
            $cutNum = round($freezeNum*0.9,2);
            //写入记录
            Db::name('bal_record')->insert([
                'number'=>-1*$freezeNum,
                'type'=>14,
                'info'=>'转换为消费积分',
                'member_id'=>$item['member_id']
            ]);
            Db::name('bal_record')->insert([
                'number'=>$cutNum,
                'type'=>15,
                'info'=>'冻结福分转换为消费积分',
                'member_id'=>$item['member_id']
            ]);
            Db::name('member')->where(['id'=>$item['member_id']])->update([
                'integral' => Db::raw('integral+'.$cutNum),
                'freeze_lot' => Db::raw('freeze_lot-'.$freezeNum),
            ]);
        }
    }
    /**
     * 顺顺福预约退款
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private function machine_refund(){
        $order = Db::name('machine_order')->field('member_id,price,status,id')->where('pay_time <DATE_SUB(NOW(),INTERVAL 1 day) and status = 1')->limit(3000)->select();
        if(!$order){
            return false;
        }
        $dataArr  = [];
        foreach ($order as $item){
            //修改订单状态
            Db::name('machine_order')->where(['id'=>$item['id']])->update([
                'status'=>2,
                'cancel_time'=>date('Y-m-d H:i:s')
            ]);
            $dataArr[] = [ //消费积分退款记录
                'number'=>$item['price'],
                'type'=>19,
                'info'=>'顺顺福未中单退款',
                'member_id'=>$item['member_id']
            ];
            //修改用户资产
            Db::name('member')->where(['id'=>$item['member_id']])->update([
                'integral' => Db::raw('integral+' . $item['price'])
            ]);
            Push::main()->machine_lot_rebate($item['member_id']);
            //Push::main()->machine_lot_rebates($item['member_id']);
            //8-12代
            
            //反佣
            $green_points = Db::name('member')->where(['id'=>$item['member_id']])->value('green_points');
            if($green_points<=0){
                continue;
            }
            
            //$num = self::$config['machine_refund'];//赠送福分数量, 原始代码，如果恢复原始就注销下面的随机数
            $num = mt_rand(1400, 1900) / 100;//补丁：随机赠送福分数量，注意数字乘以100
            $number = $green_points>$num?$num:$green_points;
            
            Db::name('member')->where(['id'=>$item['member_id']])->update([
                'green_points' => Db::raw('green_points-' . $number),
                'lot' => Db::raw('lot+' . $number)
            ]);
            $dataArr[] = [ //福分赠送记录
                'number'=>$number,
                'type'=>20,
                'info'=>'顺顺福未中单赠送福分',
                'member_id'=>$item['member_id']
            ];
            $dataArr[] = [ //绿色积分扣除记录
                'number'=>-1*$number,
                'type'=>31,
                'info'=>'顺顺福未中单赠送福分消耗',
                'member_id'=>$item['member_id']
            ];
        }
        Db::name('bal_record')->insertAll($dataArr);
    }

    
    //移动贡献积分
    public function move_to_points_history($data = '1'){//@
    
    // 记录函数开始执行的时间
    $startTime = microtime(true);
    $totalMovedRecords = 0;
    $limit = 500;
    $iterations = 100; // 设置循环次数
    //$any_time = '2024-11-03';
    for ($i = 0; $i < $iterations; $i++) {
        // 开启事务
        Db::startTrans();
        try {
            // 查询符合条件的记录
            $records = Db::name('bal_record')->where([['type', 'in', config('points_type')],['id', '<', $data]])->limit($limit)->select();//@
            // 如果没有记录，则无需进行任何操作，直接跳出循环
            if (empty($records)) {
                Db::commit(); // 提交空事务
                break;
            }
            Db::name('bal_record_points_history')->insertAll($records);//@
            $ids = array_column($records, 'id');
            Db::name('bal_record')->whereIn('id', $ids)->delete();
            $totalMovedRecords += count($records);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            echo "An error occurred during the migration (iteration {$i}): " . $e->getMessage() . "\n";
            break;
        }
    }
    // 记录结束时间
    $endTime = microtime(true);
    $totalTime = round($endTime - $startTime);
    if ($totalTime > -1){
        echo '|move_to_points_history花费时间：'.$totalTime.'秒 处理数据:'.$totalMovedRecords;
    Db::name('log')->insert(['level'=>'move_to_points_history','type'=>75,'msg'=>'花费时间：'.$totalTime.'秒 | 处理数据:'.$totalMovedRecords,'create_time'=>date('Y-m-d H:i:s')]);}//@
    }
    //移动贡献积分
    
    //移动消费积分
    public function move_to_integral_history($data = '1'){
    
    // 记录函数开始执行的时间
    $startTime = microtime(true);
    $totalMovedRecords = 0;
    $limit = 500;
    $iterations = 100; // 设置循环次数
    //$any_time = '2024-11-03';
    for ($i = 0; $i < $iterations; $i++) {
        // 开启事务
        Db::startTrans();
        try {
            // 查询符合条件的记录
            $records = Db::name('bal_record')->where([['type', 'in', config('integral_type')],['id', '<', $data]])->limit($limit)->select();
            // 如果没有记录，则无需进行任何操作，直接跳出循环
            if (empty($records)) {
                Db::commit(); // 提交空事务
                break;
            }
            Db::name('bal_record_integral_history')->insertAll($records);
            $ids = array_column($records, 'id');
            Db::name('bal_record')->whereIn('id', $ids)->delete();
            $totalMovedRecords += count($records);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            echo "An error occurred during the migration (iteration {$i}): " . $e->getMessage() . "\n";
            break;
        }
    }
    // 记录结束时间
    $endTime = microtime(true);
    $totalTime = round($endTime - $startTime);
    if ($totalTime > -1){
        echo '|move_to_integral_history花费时间：'.$totalTime.'秒 处理数据:'.$totalMovedRecords;
    Db::name('log')->insert(['level'=>'move_to_integral_history','type'=>75,'msg'=>'花费时间：'.$totalTime.'秒 | 处理数据:'.$totalMovedRecords,'create_time'=>date('Y-m-d H:i:s')]);}
    }
    //移动消费积分
    
    //移动冻结积分
    public function move_to_freeze_lot_history($data = '1'){//@
    
    // 记录函数开始执行的时间
    $startTime = microtime(true);
    $totalMovedRecords = 0;
    $limit = 500;
    $iterations = 100; // 设置循环次数
    //$any_time = '2024-11-03';
    for ($i = 0; $i < $iterations; $i++) {
        // 开启事务
        Db::startTrans();
        try {
            // 查询符合条件的记录
            $records = Db::name('bal_record')->where([['type', 'in', config('freeze_lot')],['id', '<', $data]])->limit($limit)->select();//@
            // 如果没有记录，则无需进行任何操作，直接跳出循环
            if (empty($records)) {
                Db::commit(); // 提交空事务
                break;
            }
            Db::name('bal_record_freeze_history')->insertAll($records);//@
            $ids = array_column($records, 'id');
            Db::name('bal_record')->whereIn('id', $ids)->delete();
            $totalMovedRecords += count($records);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            echo "An error occurred during the migration (iteration {$i}): " . $e->getMessage() . "\n";
            break;
        }
    }
    // 记录结束时间
    $endTime = microtime(true);
    $totalTime = round($endTime - $startTime);
    if ($totalTime > -1){
        echo '|move_to_freeze_lot_history花费时间：'.$totalTime.'秒 处理数据:'.$totalMovedRecords;
    Db::name('log')->insert(['level'=>'move_to_freeze_lot_history','type'=>75,'msg'=>'花费时间：'.$totalTime.'秒 | 处理数据:'.$totalMovedRecords,'create_time'=>date('Y-m-d H:i:s')]);}//@
    }
    //移动冻结积分
    
    //移动绿色积分
    public function move_to_green_points_history($data = '1'){//@
    
    // 记录函数开始执行的时间
    $startTime = microtime(true);
    $totalMovedRecords = 0;
    $limit = 500;
    $iterations = 100; // 设置循环次数
    //$any_time = '2024-11-03';
    for ($i = 0; $i < $iterations; $i++) {
        // 开启事务
        Db::startTrans();
        try {
            // 查询符合条件的记录
            $records = Db::name('bal_record')->where([['type', 'in', config('green_points')],['id', '<', $data]])->limit($limit)->select();//@
            // 如果没有记录，则无需进行任何操作，直接跳出循环
            if (empty($records)) {
                Db::commit(); // 提交空事务
                break;
            }
            Db::name('bal_record_green_history')->insertAll($records);//@
            $ids = array_column($records, 'id');
            Db::name('bal_record')->whereIn('id', $ids)->delete();
            $totalMovedRecords += count($records);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            echo "An error occurred during the migration (iteration {$i}): " . $e->getMessage() . "\n";
            break;
        }
    }
    // 记录结束时间
    $endTime = microtime(true);
    $totalTime = round($endTime - $startTime);
    if ($totalTime > -1){
        echo '|move_to_green_points_history花费时间：'.$totalTime.'秒 处理数据:'.$totalMovedRecords;
    Db::name('log')->insert(['level'=>'move_to_green_points_history','type'=>75,'msg'=>'花费时间：'.$totalTime.'秒 | 处理数据:'.$totalMovedRecords,'create_time'=>date('Y-m-d H:i:s')]);}//@
    }
    //移动绿色积分
    
    //移动福分积分
    public function move_to_lot_type_history($data = '1'){//@
    
    // 记录函数开始执行的时间
    $startTime = microtime(true);
    $totalMovedRecords = 0;
    $limit = 500;
    $iterations = 100; // 设置循环次数
    //$any_time = '2024-11-03';
    for ($i = 0; $i < $iterations; $i++) {
        // 开启事务
        Db::startTrans();
        try {
            // 查询符合条件的记录
            $records = Db::name('bal_record')->where([['type', 'in', config('lot_type')],['id', '<', $data]])->limit($limit)->select();//@
            // 如果没有记录，则无需进行任何操作，直接跳出循环
            if (empty($records)) {
                Db::commit(); // 提交空事务
                break;
            }
            Db::name('bal_record_lot_history')->insertAll($records);//@
            $ids = array_column($records, 'id');
            Db::name('bal_record')->whereIn('id', $ids)->delete();
            $totalMovedRecords += count($records);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            echo "An error occurred during the migration (iteration {$i}): " . $e->getMessage() . "\n";
            break;
        }
    }
    // 记录结束时间
    $endTime = microtime(true);
    $totalTime = round($endTime - $startTime);
    if ($totalTime > -1){
        echo '|move_to_lot_type_history花费时间：'.$totalTime.'秒 处理数据:'.$totalMovedRecords;
    Db::name('log')->insert(['level'=>'move_to_lot_type_history','type'=>75,'msg'=>'花费时间：'.$totalTime.'秒 | 处理数据:'.$totalMovedRecords,'create_time'=>date('Y-m-d H:i:s')]);}//@
    }
    //移动福分积分
    
    public function move_data(){
    
    $currentTime = date('H:i');
    if (!(date('H:i', strtotime('04:30')) <= $currentTime && $currentTime <= date('H:i', strtotime('07:30')))) {
        echo '不在执行时间范围内(4:30-7:30)-跳过';
        return;
    } 
        
    $startTime = microtime(true);
    $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
    $endDate = date('Y-m-d 00:10:10', strtotime('-30 days'));
     
    $query = Db::name('bal_record')
        ->where('add_time', 'between', [$startDate, $endDate])
        ->order('add_time', 'asc') // 实际上在这个范围内只需要一条，所以排序可能是多余的
        ->limit(1)
        ->field('id');
    
    $result = $query->find();
    $minId = isset($result['id']) ? $result['id'] : null;
    // 检查是否找到了记录
    if ($minId !== null) {$data = $minId;} else {$data = 1;}

    $endTime = microtime(true);
    $totalTime = round($endTime - $startTime);

        echo $data.'*'.$startDate.'*'.$totalTime.'*';
        $this->move_to_points_history($data);
        echo '*';
        $this->move_to_integral_history($data);
        echo '*';
        $this->move_to_freeze_lot_history($data);
        echo '*';
        $this->move_to_green_points_history($data);
        echo '*';
        $this->move_to_lot_type_history($data);
        
    }

    public function data_center(){
        
        // 获取当前时间
        $currentTime = date('H:i');
        
        // 检查当前时间是否在4:30到23:00之间
        if (!(date('H:i', strtotime('02:00')) <= $currentTime && $currentTime <= date('H:i', strtotime('23:30')))) {
            // 如果不在指定时间范围内，则直接返回
            echo '不在执行时间范围内(02:00-23:30)-跳过';
            return;
        } 
        
        $startTime = microtime(true);
        
        $type = input('get.type');
        
        // 初始化明天的日期
        $tomorrow = date('Y-m-d', strtotime('+1 day'));  
        // 初始化今天的日期  
        $today = date('Y-m-d');  
        // 昨天的日期  
        $yesterday = date('Y-m-d', strtotime('-1 day'));  
        // 7天前的日期  
        $sevenDaysAgo = date('Y-m-d', strtotime('-7 day'));  
        // 30天前的日期  
        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 day'));  
        // 本月1号的日期  
        $firstDayOfMonth = date('Y-m-01');  
        // 本年1号的日期  
        $firstDayOfYear = date('Y-01-01');  
        // 上个月的一号日期
        $lastMonthFirstDay = date('Y-m-01', strtotime('-1 month'));
          
        // 根据$type设置不同的时间范围  
        switch ($type) {  
            case 0:  
                // 今天到明天（通常不会包含未来日期，这里假设为“今天”作为示例）  
               $data = [$today,$tomorrow];
               
               $today_min_id = cache('$today_min_id'.$data[0]);
                // 判断缓存是否存在
                if ($today_min_id !== false) {
                    echo '读取缓存今日最小ID值'. "\n";
                } else {
                    // 缓存不存在或者不是数组，执行其他逻辑
                    echo '缓存不存在 重新缓存。'. "\n";
                    $today_min_id = Db::name('bal_record')
                    ->where('add_time', 'gt', $data[0])
                    ->min('id');
                    cache('$today_min_id'.$data[0], $today_min_id, 0);
                }
               
               $wheres[] = ['id','egt',$today_min_id];
                break;  
            case 1:  
                // 昨天到今天  
                $data = [$yesterday, $today];  
                $wheres[] = ['add_time','between',$data];
                break;  
            case 2:  
                // 7天前到昨天  
                $data = [$sevenDaysAgo, $yesterday];  
                $wheres[] = ['add_time','between',$data];
                break;  
            case 3:  
                // 30天前到昨天  
                $data = [$thirtyDaysAgo, $yesterday];  
                $wheres[] = ['add_time','between',$data];
                break;  
            case 4:  
                // 本月1号到昨天  
                $data = [$firstDayOfMonth, $yesterday];  
                $wheres[] = ['add_time','between',$data];
                break;  
            case 5:  
                // 上个月1号到本月1号（不包含本月1号）  
                $data = [$lastMonthFirstDay, $firstDayOfMonth];  
                $wheres[] = ['add_time','between',$data];
                break;      
            case 6:  
                // 本年1号到昨天  
                $data = [$firstDayOfYear, $yesterday];  
                $wheres[] = ['add_time','between',$data];
                break;  
            case 7:
                $data = ['2023-01-01', $yesterday];
                $wheres[] = ['add_time','between',$data];
                break;
                //全部历史到昨天
            default:  
                //默认值
                $data = ['2023-01-01', $yesterday];
                $wheres[] = ['add_time','between',$data];
                break;  
            }    
        
        //总计充值金额
        $rechargeBal = Db::name('prestore_recharge')->where([['pay_time','between',$data]])->sum('amount');
        cache('$rechargeBal'.$type, $rechargeBal, 86400);
        //消费积分
        /*
        $integral = Db::name('bal_record')->where([['type','in',config('integral_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
        */
        $integral_main = Db::name('bal_record')->where([['type','in','6,8,15,19,30,32'],['number','gt',0]])->where($wheres)->sum('number');
        $integral_history = Db::name('bal_record_integral_history')->where([['type','in','6,8,15,19,30,32'],['number','gt',0]])->where($wheres)->sum('number');
        $integral = $integral_main + $integral_history;
        cache('$integral'.$type, $integral, 86400);
        //贡献积分
        /*
        $points = Db::name('bal_record')->where([['type','in',config('points_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
        */
        $points_main = Db::name('bal_record')->where([['type','eq','24']])->where($wheres)->sum('number');
        $points_history = Db::name('bal_record_points_history')->where([['type','eq','24']])->where($wheres)->sum('number');
        $points = $points_main + $points_history;
        cache('$points'.$type, $points, 86400);
        //福分
        /*
        $lot = Db::name('bal_record')->where([['type','in',config('lot_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
        */
        $lot_main = Db::name('bal_record')->where([['type','in','20,21,25,26,27,35,38']])->where($wheres)->sum('number');
        $lot_history = Db::name('bal_record_lot_history')->where([['type','in','20,21,25,26,27,35,38']])->where($wheres)->sum('number');
        $lot = $lot_main + $lot_history;
        cache('$lot'.$type, $lot, 86400);
        //冻结福分
        /*
        $freeze_lot = Db::name('bal_record')->where([['type','in',config('freeze_lot')],['number','gt',0],['add_time','between',$data]])->sum('number');
        */
        $freeze_main = Db::name('bal_record')->where([['type','eq','28']])->where($wheres)->sum('number');
        $freeze_history = Db::name('bal_record_freeze_history')->where([['type','eq','28']])->where($wheres)->sum('number');
        $freeze_lot = $freeze_main + $freeze_history;
        cache('$freeze_lot'.$type, $freeze_lot, 86400);
        //绿色积分
        /*
        $green_points = Db::name('bal_record')->where([['type','in',config('green_points')],['number','gt',0],['add_time','between',$data]])->sum('number');
        cache('$green_points'.$type, $green_points, 86400);
        */
        $green_points_main = Db::name('bal_record')->where([['type','eq','23']])->where($wheres)->sum('number');
        $green_points_history = Db::name('bal_record_green_history')->where([['type','eq','23']])->where($wheres)->sum('number');
        $green_points = $green_points_main + $green_points_history;
        cache('$green_points'.$type, $green_points, 86400);
        //提现
        $withdraw = Db::name('commission_withdraw')->where([['create_time','between',$data],['status','in',[0,1,2,3,4]]])->sum('money');
        cache('$withdraw'.$type, $withdraw, 86400);
        //新增会员
        $newMember = Db::name('member')->where([['create_time','between',$data]])->count('id');
        cache('$newMember'.$type, $newMember, 86400);
        //体验
        $Member = Db::name('member')->where([['create_time','between',$data],['is_vip','eq',0]])->count('id');
        cache('$Member'.$type, $Member, 86400);
        //正式
        $vipMember = Db::name('mall_order')->cache(60)->where([['pay_time','between',$data],['goods_type','eq',5]])->count('order_id');
        cache('$vipMember'.$type, $vipMember, 86400);
        //城市代理
        $city_agency = Db::name('vip_log')->where([['create_time','between',$data],['type','eq',1]])->count('id');
        cache('$city_agency'.$type, $city_agency, 86400);
        //驿站代理
        $station_agent = Db::name('vip_log')->where([['create_time','between',$data],['type','eq',2]])->count('id');
        cache('$station_agent'.$type, $station_agent, 86400);
        //行政区代理
        $v1 = Db::name('vip_log')->where([['create_time','between',$data],['type','eq',3],['vip_level','eq',1]])->count('id');
        cache('$v1'.$type, $v1, 86400);
        $v2 = Db::name('vip_log')->where([['create_time','between',$data],['type','eq',3],['vip_level','eq',2]])->count('id');
        cache('$v2'.$type, $v2, 86400);
        //预约中单
        $machineOrder = Db::name('machine_order')->where([['pay_time','between',$data],['status','eq',3]])->count('id');
        cache('$machineOrder'.$type, $machineOrder, 86400);
        //中单金额
        $machineBal = Db::name('machine_order')->where([['pay_time','between',$data],['status','eq',3]])->sum('price');
        cache('$machineBal'.$type, $machineBal, 86400);
        //总预约单量
        $Order = Db::name('machine_order')->where([['pay_time','between',$data]])->count('id');
        cache('$Order'.$type, $Order, 86400);
        //总预约额
        $OrderBal = Db::name('machine_order')->where([['pay_time','between',$data]])->sum('price');
        cache('$OrderBal'.$type, $OrderBal, 86400);
        //中单发放福分
        $order_lot = Db::name('bal_record')->where([['type','in',[27]],['number','gt',0]])->where($wheres)->sum('number');
        cache('$order_lot'.$type, $order_lot, 86400);
        //未中单
        $orderNo = Db::name('machine_order')->where([['pay_time','between',$data],['status','in',[1,2]]])->count('id');
        cache('$orderNo'.$type, $orderNo, 86400);
        //未中单发放福分
        $sendLot = Db::name('bal_record')->where([['type','in',[20]],['number','gt',0]])->where($wheres)->sum('number');
        cache('$sendLot'.$type, $sendLot, 86400);
        //商城成单
        $mallOrder = Db::name('mall_order')->cache(60)->where([['add_time','between',$data],['order_status','in',[1,2,3]]])->count('order_id');
        cache('$mallOrder'.$type, $mallOrder, 86400);
        //成交额
        $mallBal = Db::name('mall_order')->cache(60)->where([['add_time','between',$data],['order_status','in',[1,2,3]]])->sum('order_amount');
        cache('$mallBal'.$type, $mallBal, 86400);
        //兑换额
        $exchange = Db::name('mall_order')->cache(60)->where([['add_time','between',$data],['order_status','in',[1,2,3]],['goods_type','eq',3]])->sum('order_amount');
        cache('$exchange'.$type, $exchange, 86400);
        //养生专区商品
        $yangsheng = Db::name('mall_order o')->cache(60)->leftJoin('mall_product g','g.id = o.goods_id')->leftJoin('mall_dettype d','d.pid = g.id')->where([['o.add_time','between',$data],['o.order_status','in',[1,2,3]],['d.prid','eq',8]])->sum('o.order_amount');
        cache('$yangsheng'.$type, $yangsheng, 86400);
        $jiayong = Db::name('mall_order o')->cache(60)->leftJoin('mall_product g','g.id = o.goods_id')->leftJoin('mall_dettype d','d.pid = g.id')->where([['o.add_time','between',$data],['o.order_status','in',[1,2,3]],['d.prid','eq',11]])->sum('o.order_amount');
        cache('$jiayong'.$type, $jiayong, 86400);
        $nongte = Db::name('mall_order o')->cache(60)->leftJoin('mall_product g','g.id =o.goods_id')->leftJoin('mall_dettype d','d.pid = g.id')->where([['o.add_time','between',$data],['o.order_status','in',[1,2,3]],['d.prid','eq',16]])->sum('o.order_amount');
        cache('$nongte'.$type, $nongte, 86400);
        //健康大使
        $totalNum = Db::name('mall_weight_list')->where([['create_time','between',$data]])->count('id');
        cache('$totalNum'.$type, $totalNum, 86400);
        $weight_list = Db::name('mall_weight m')->where([['w.create_time','between',$data]])->field('m.name,count(w.id) as num')->leftJoin('mall_weight_list w','m.id = w.weight_id')->group('m.id')->select();
        cache('$weight_list'.$type, $weight_list, 86400);
       
        $endTime = microtime(true); // 记录结束时间  
        echo number_format($endTime - $startTime, 2).'秒';
    }


    // 移动绿色积分
    public function move_to_green_points_history_member_id(){//@
        // 记录函数开始执行的时间
        $startTime = microtime(true);
        $totalMovedRecords = 0;
        $maxLimitPerMember = 6000; // 每个 member_id 最多处理的记录数上限
        $minLimitPerMember = 500; // 每个 member_id 至少保留的记录数
        $batchSize = 6000; // 每次循环处理的最大记录数
        $maxMemberIterations = 3; // 每次循环最多处理的 member_id 数量
        
        // 获取所有符合条件的 member_id 及其最新记录
        $memberRecords = Db::name('bal_record')
            ->where('type', 'in', config('green_points'))
            ->field('member_id, MIN(add_time) as latest_time')
            ->group('member_id')
            ->select();
         
/*      
// 添加数据到 $memberRecords 数组
$memberIds = [3692,1945];
$memberRecords = []; 
foreach ($memberIds as $memberIdx) {
    $memberRecords[] = ['member_id' => $memberIdx];
}
// 添加数据到 $memberRecords 数组
*/  
        $memberIds = array_column($memberRecords, 'member_id');
    
        // 定义一个函数来获取某个 member_id 的最新 N 条记录
        $getLatestRecords = function($memberId, $limit) {
            return Db::name('bal_record')
                ->where('member_id', $memberId)
                ->where('type', 'in', config('green_points'))
                ->order('add_time ASC')
                ->limit($limit)
                ->select();
        };
    
        $iterations = 0; // 循环计数器，用于限制 member_id 的处理数量
    
        while (count($memberIds) > 0 && $iterations < $maxMemberIterations) {
            Db::startTrans();
    
            try {
                $processedMemberIds = [];
                $remainingToMove = $batchSize;
    
                foreach ($memberIds as $index => $memberId) {
                    // 获取该 member_id 的所有绿色积分记录
                    $allRecords = Db::name('bal_record')
                        ->where('member_id', $memberId)
                        ->where('type', 'in', config('green_points'))
                        ->order('add_time ASC')
                        ->select();
    
                    $totalRecordCount = count($allRecords);
    
                    // 确定保留的记录数和实际要移动的记录数
                    $recordsToKeep = min($totalRecordCount, $minLimitPerMember);
                    $recordsToMove = max(0, $totalRecordCount - $recordsToKeep);
    
                    // 获取要移动的最新记录，但不超过剩余可移动的数量和实际限制
                    $latestRecordsToMove = array_slice($allRecords, 0, min($recordsToMove, $remainingToMove));
    
                    if (empty($latestRecordsToMove)) {
                        unset($memberIds[$index]); // 如果没有记录要移动，则从数组中移除该 member_id
                        continue;
                    }
    
                    // 插入到历史表并删除原记录
                    Db::name('bal_record_green_history')->insertAll($latestRecordsToMove);
                    $ids = array_column($latestRecordsToMove, 'id');
                    Db::name('bal_record')->whereIn('id', $ids)->delete();
    
                    $totalMovedRecords += count($latestRecordsToMove);
                    $remainingToMove -= count($latestRecordsToMove);
    
                    // 输出当前 member_id 和移动的记录数
                    echo "Member ID: $memberId, Moved Records: " . count($latestRecordsToMove) . "\n";
    
                    // 如果已经移动了足够的记录或没有剩余的可移动记录，则退出循环
                    if ($remainingToMove <= 0) {
                        break;
                    }
    
                    $processedMemberIds[] = $memberId; // 记录成功处理的 member_id
                }
    
                // 从 memberIds 中移除已处理的 member_id
                $memberIds = array_diff($memberIds, $processedMemberIds);
    
                $iterations++; // 增加循环计数器
                Db::commit();
    
            } catch (\Exception $e) {
                Db::rollback();
                echo "An error occurred during the migration: " . $e->getMessage() . "\n";
                break;
            }
        }
    
        // 记录结束时间
        $endTime = microtime(true);
        $totalTime = round($endTime - $startTime);
        echo '数据处理：'.$totalMovedRecords. "\n";
        if ($totalTime > -1){
            Db::name('log')->insert([
                'level' => 'move_to_green_points_history',
                'type' => 75,
                'msg' => '花费时间：'.$totalTime.'秒 | 处理数据:'.$totalMovedRecords,
                'create_time' => date('Y-m-d H:i:s')
            ]);
        }
    }
    //移动绿色积分
    
    // 移动福分积分
    public function move_to_lot_type_history_member_id(){//@
        // 记录函数开始执行的时间
        $startTime = microtime(true);
        $totalMovedRecords = 0;
        $maxLimitPerMember = 6000; // 每个 member_id 最多处理的记录数上限
        $minLimitPerMember = 500; // 每个 member_id 至少保留的记录数
        $batchSize = 6000; // 每次循环处理的最大记录数
        $maxMemberIterations = 3; // 每次循环最多处理的 member_id 数量
        
        // 获取所有符合条件的 member_id 及其最新记录
        $memberRecords = Db::name('bal_record')
            ->where('type', 'in', config('lot_type'))
            ->field('member_id, MIN(add_time) as latest_time')
            ->group('member_id')
            ->select();
        
/*        
// 添加数据到 $memberRecords 数组
$memberIds = [3692,1945];
$memberRecords = []; 
foreach ($memberIds as $memberIdx) {
    $memberRecords[] = ['member_id' => $memberIdx];
}
// 添加数据到 $memberRecords 数组
*/
        $memberIds = array_column($memberRecords, 'member_id');
    
        // 定义一个函数来获取某个 member_id 的最新 N 条记录
        $getLatestRecords = function($memberId, $limit) {
            return Db::name('bal_record')
                ->where('member_id', $memberId)
                ->where('type', 'in', config('lot_type'))
                ->order('add_time ASC')
                ->limit($limit)
                ->select();
        };
    
        $iterations = 0; // 循环计数器，用于限制 member_id 的处理数量
    
        while (count($memberIds) > 0 && $iterations < $maxMemberIterations) {
            Db::startTrans();
    
            try {
                $processedMemberIds = [];
                $remainingToMove = $batchSize;
    
                foreach ($memberIds as $index => $memberId) {
                    // 获取该 member_id 的所有福分积分记录
                    $allRecords = Db::name('bal_record')
                        ->where('member_id', $memberId)
                        ->where('type', 'in', config('lot_type'))
                        ->order('add_time ASC')
                        ->select();
    
                    $totalRecordCount = count($allRecords);
    
                    // 确定保留的记录数和实际要移动的记录数
                    $recordsToKeep = min($totalRecordCount, $minLimitPerMember);
                    $recordsToMove = max(0, $totalRecordCount - $recordsToKeep);
    
                    // 获取要移动的最新记录，但不超过剩余可移动的数量和实际限制
                    $latestRecordsToMove = array_slice($allRecords, 0, min($recordsToMove, $remainingToMove));
    
                    if (empty($latestRecordsToMove)) {
                        unset($memberIds[$index]); // 如果没有记录要移动，则从数组中移除该 member_id
                        continue;
                    }
    
                    // 插入到历史表并删除原记录
                    Db::name('bal_record_lot_history')->insertAll($latestRecordsToMove);
                    $ids = array_column($latestRecordsToMove, 'id');
                    Db::name('bal_record')->whereIn('id', $ids)->delete();
    
                    $totalMovedRecords += count($latestRecordsToMove);
                    $remainingToMove -= count($latestRecordsToMove);
    
                    // 输出当前 member_id 和移动的记录数
                    echo "Member ID: $memberId, Moved Records: " . count($latestRecordsToMove) . "\n";
    
                    // 如果已经移动了足够的记录或没有剩余的可移动记录，则退出循环
                    if ($remainingToMove <= 0) {
                        break;
                    }
    
                    $processedMemberIds[] = $memberId; // 记录成功处理的 member_id
                }
    
                // 从 memberIds 中移除已处理的 member_id
                $memberIds = array_diff($memberIds, $processedMemberIds);
    
                $iterations++; // 增加循环计数器
                Db::commit();
    
            } catch (\Exception $e) {
                Db::rollback();
                echo "An error occurred during the migration: " . $e->getMessage() . "\n";
                break;
            }
        }
    
        // 记录结束时间
        $endTime = microtime(true);
        $totalTime = round($endTime - $startTime);
        echo '数据处理：'.$totalMovedRecords. "\n";
        if ($totalTime > -1){
            Db::name('log')->insert([
                'level' => 'move_to_lot_points_history',
                'type' => 75,
                'msg' => '花费时间：'.$totalTime.'秒 | 处理数据:'.$totalMovedRecords,
                'create_time' => date('Y-m-d H:i:s')
            ]);
        }
    }
    //CREATE INDEX idx_member_type_addtime ON `bal_record` (`member_id`, `type`, `add_time`);
    // 移动福分积分
    
    
    
    //移动积分到历史表
    public function move_to_history_member_id($_config,$_history,$minLimitPerMember){//@
        // 记录函数开始执行的时间
        $startTime = microtime(true);
        $totalMovedRecords = 0;
        $maxLimitPerMember = 6000; // 每个 member_id 最多处理的记录数上限
        $minLimitPerMember = $minLimitPerMember; // 每个 member_id 至少保留的记录数
        $batchSize = 6000; // 每次循环处理的最大记录数
        $maxMemberIterations = 3; // 每次循环最多处理的 member_id 数量
        
    // 尝试从缓存中获取数据
    $memberRecords = cache('$memberRecords'.$_history);
    // 判断缓存是否存在并且是一个数组
    if ($memberRecords !== false && is_array($memberRecords)) {
        echo '读取缓存'. "\n";
    } else {
        // 缓存不存在或者不是数组，执行其他逻辑
        echo '缓存不存在 重新缓存。'. "\n";
        $memberRecords = Db::name('bal_record')
        ->where('type', 'in', $_config)
        ->field('member_id')
        ->group('member_id')
        ->having('COUNT(*) > '.$minLimitPerMember)
        ->select();
        cache('$memberRecords'.$_history, $memberRecords, 10800);
    }
     
/*     
// 添加数据到 $memberRecords 数组
$memberIds = [14239,12118];
$memberRecords = []; 
foreach ($memberIds as $memberIdx) {
    $memberRecords[] = ['member_id' => $memberIdx];
}
// 添加数据到 $memberRecords 数组
*/

        $memberIds = array_column($memberRecords, 'member_id');
    
        
        $iterations = 0; // 循环计数器，用于限制 member_id 的处理数量
    
        while (count($memberIds) > 0 && $iterations < $maxMemberIterations) {
            Db::startTrans();
    
            try {
                $processedMemberIds = [];
                $remainingToMove = $batchSize;
    
                foreach ($memberIds as $index => $memberId) {
                    // 获取该 member_id 的所有积分记录
                    $allRecords = Db::name('bal_record')
                        ->where('member_id', $memberId)
                        ->where('type', 'in', $_config)
                        ->order('add_time ASC')
                        ->select();
    
                    $totalRecordCount = count($allRecords);
    
                    // 确定保留的记录数和实际要移动的记录数
                    $recordsToKeep = min($totalRecordCount, $minLimitPerMember);
                    $recordsToMove = max(0, $totalRecordCount - $recordsToKeep);
    
                    // 获取要移动的最新记录，但不超过剩余可移动的数量和实际限制
                    $latestRecordsToMove = array_slice($allRecords, 0, min($recordsToMove, $remainingToMove));
    
                    if (empty($latestRecordsToMove)) {
                        unset($memberIds[$index]); // 如果没有记录要移动，则从数组中移除该 member_id
                        continue;
                    }
    
                    // 插入到历史表并删除原记录
                    Db::name($_history)->insertAll($latestRecordsToMove);
                    $ids = array_column($latestRecordsToMove, 'id');
                    Db::name('bal_record')->whereIn('id', $ids)->delete();
    
                    $totalMovedRecords += count($latestRecordsToMove);
                    $remainingToMove -= count($latestRecordsToMove);
    
                    // 输出当前 member_id 和移动的记录数
                    echo "Member ID: $memberId, Moved Records: " . count($latestRecordsToMove) . "\n";
    
                    // 如果已经移动了足够的记录或没有剩余的可移动记录，则退出循环
                    if ($remainingToMove <= 0) {
                        break;
                    }
    
                    $processedMemberIds[] = $memberId; // 记录成功处理的 member_id
                }
    
                // 从 memberIds 中移除已处理的 member_id
                $memberIds = array_diff($memberIds, $processedMemberIds);
    
                $iterations++; // 增加循环计数器
                Db::commit();
    
            } catch (\Exception $e) {
                Db::rollback();
                echo "An error occurred during the migration: " . $e->getMessage() . "\n";
                break;
            }
        }
    
        // 记录结束时间
        $endTime = microtime(true);
        $totalTime = round($endTime - $startTime);
        echo $_history.' 数据处理：'.$totalMovedRecords. "\n";
        if ($totalTime > 1){
            Db::name('log')->insert([
                'level' => $_history,
                'type' => 75,
                'msg' => '花费时间：'.$totalTime.'秒 | 处理数据:'.$totalMovedRecords,
                'create_time' => date('Y-m-d H:i:s')
            ]);
        }
    }
    //移动积分到历史表
    
    
    
    
    public function move_data_member_id(){
    //cache('move_tag', 0, 0);
    if (cache('move_tag') == 1){
        echo '上一个任务还在运行中';
        return;
    }
    $currentTime = date('H:i');
    if (!(date('H:i', strtotime('04:30')) <= $currentTime && $currentTime <= date('H:i', strtotime('07:17')))) {
        echo '不在执行时间范围内(4:30-07:17)-跳过';
        return;
    } 
    cache('move_tag', 1, 0);    
    $startTime = microtime(true);
    	
        $this->move_to_history_member_id('1,6,7,9,10,12,15,18,19,29,30,32,41','bal_record_integral_history','200');//移动消费积分
        $this->move_to_history_member_id('2,16,24','bal_record_points_history','100');//移动贡献积分
        $this->move_to_history_member_id('3,22,23,31,34,36,37,39,40','bal_record_green_history','200');//移动绿色积分
        $this->move_to_history_member_id('4,11,13,17,20,21,25,26,27,33,35,38','bal_record_lot_history','200');//移动福分积分
        $this->move_to_history_member_id('5,14,28','bal_record_freeze_history','200');//移动冻结积分
        
        /*
	    $this->move_to_lot_type_history_member_id();
        $this->move_to_green_points_history_member_id();
        */
    $endTime = microtime(true);
    $totalTime = round($endTime - $startTime);
    echo 'Time：'.$totalTime. "\n";
    cache('move_tag', 0, 0);   
    }
    
    
    public static function updatePayTimeToFiftyMinutes()
    {
        $twoDaysAgo = date('Y-m-d', strtotime('-2 days'));
        $sql = "UPDATE `machine_order` SET pay_time = DATE_FORMAT(pay_time, '%Y-%m-%d %H:50:00') WHERE pay_time > '{$twoDaysAgo}' AND MINUTE(pay_time) > 50";
        $result = Db::execute($sql);
        return $result;
    }

}