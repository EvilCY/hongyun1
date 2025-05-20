<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2019/10/13
 * Time: 20:36
 */

namespace app\index\controller;
use app\lib\Curl;
use think\Controller;
use think\Db;
use think\Exception;

class Push extends Controller{
    static private $config;
    public function __construct()
    {
        parent::__construct();
        self::$config = $this->setConfig();
    }
    private function setConfig(){
        return Db::name('config')->cache('web_conf',600)->column('val','key');
    }
    static function main(){
        return new self();
    }

    /**
     * 发放新人礼包
     * @param $member_id
     * @return false|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function newMember($member_id){
        $coupon_info = Db::name('coupon_pick_conf')->where([['id','eq',1],['status','eq',1]])->find();
        if(!$coupon_info){
           return false;
        }
        if($coupon_info['time_type'] == 1){
            $n_time = date('Y-m-d H:i:s');
            if(!($n_time < $coupon_info['end_time'])){
                return false;
            }
            $start_time = $coupon_info['start_time'];
            $end_time = $coupon_info['end_time'];

        }else{
            $start_time = date('Y-m-d');
            $end_time = date('Y-m-d',strtotime("+".$coupon_info['day']." day"));
        }
        $insertData = [
                'title'=>$coupon_info['title'],
                'coupon_id'=>$coupon_info['id'],
                'imgurl'=>$coupon_info['imgurl'],
                'member_id'=>$member_id,
                'goods_id'=>$coupon_info['goods_id'],
                'money'=>$coupon_info['money'],
                'start_time'=>$start_time,
                'end_time'=>$end_time
            ];
        Db::name('coupon_pick')->insert($insertData);
    }
    /**
     * 行政区代理人返佣
     * @param $member_id 用户ID
     * @param $num 数量
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function administration_commission($member_id,$num){
        $lotNum = round($num*self::$config['administration_commission'],2);
        //行政区代理人返佣
        $member_info = Db::name('member')->field('tel,area,city,id_path')->where(['id'=>$member_id])->find();
        $city_agency = Db::name('member')->field('id,green_points')->where(['city'=>$member_info['city'],'level'=>2])->find();
        $area_agency = Db::name('member')->field('id,green_points')->where(['area'=>$member_info['area'],'level'=>1])->find();
        if($city_agency&&$lotNum>0.01){
            $lot = $lotNum>$city_agency['green_points']?$city_agency['green_points']:$lotNum;
            if($lot){
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>$lot,
                    'from_id'=>$member_id,
                    'type'=>26,
                    'info'=>'市级行政区代理人佣金',
                    'member_id'=>$city_agency['id']
                ]);
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>-1*$lot,
                    'from_id'=>'',
                    'type'=>40,
                    'info'=>'市级行政区代理人佣金消耗',
                    'member_id'=>$city_agency['id']
                ]);
                Db::name('member')->where(['id'=>$city_agency['id']])->update([
                    'lot' => Db::raw('lot+' . $lot),
                    'green_points' => Db::raw('green_points-' . $lot)
                ]);
            }
        }
        if($area_agency&&$lotNum>0.01){
            $lots = $lotNum>$area_agency['green_points']?$area_agency['green_points']:$lotNum;
            if($lots){
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>$lots,
                    'from_id'=>$member_id,
                    'type'=>26,
                    'info'=>'区县级行政区代理人佣金',
                    'member_id'=>$area_agency['id']
                ]);
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>-1*$lots,
                    'from_id'=>'',
                    'type'=>40,
                    'info'=>'区县级行政区代理人佣金消耗',
                    'member_id'=>$area_agency['id']
                ]);
                Db::name('member')->where(['id'=>$area_agency['id']])->update([
                    'lot' => Db::raw('lot+' . $lots),
                    'green_points' => Db::raw('green_points-' . $lots)
                ]);
            }
        }
    }
    public function city_commission($member_id,$num){
        $member_info = Db::name('member')->field('tel,area,city,id_path')->where(['id'=>$member_id])->find();
        //城市代理人返佣
        $agency = [self::$config['city_commission_ratio'],self::$config['city_commission_ratio1']];
        $idstr = $member_info['id_path'].$member_id;
        $cityMember = Db::name('member')->field('id,green_points')->where([['id','in',$idstr],['city_agency','eq',1]])->limit(2)->order('depth desc')->select();
        if ($cityMember){
            foreach ($cityMember as$key=>$item){
                $number = round($num*$agency[$key],2);
                if($item['green_points']<=0||$number<0.01){
                    continue;
                }
                $city_lot = $number>$item['green_points']?$item['green_points']:$number;
                Db::name('member')->where(['id'=>$item['id']])->update([
                    'lot' => Db::raw('lot+' . $city_lot),
                    'green_points' => Db::raw('green_points-' . $city_lot)
                ]);
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>$city_lot,
                    'from_id'=>$member_id,
                    'type'=>26,
                    'info'=>'城市代理人团队购物佣金',
                    'member_id'=>$item['id']
                ]);
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>-1*$city_lot,
                    'from_id'=>'',
                    'type'=>40,
                    'info'=>'城市代理人团队购物佣金消耗',
                    'member_id'=>$item['id']
                ]);

            }
        }
        //驿站代理人返佣
        $station_agent = Db::name('member')->field('id,green_points')->where([['id','in',$idstr],['station_agent','eq',1]])->limit(2)->order('depth desc')->select();
        $agent = [self::$config['station_agent_ratio'],self::$config['station_agent_ratio1']];
        if ($station_agent){
            foreach ($station_agent as$key=>$item){
                $numbers = round($num*$agent[$key],2);
                if($item['green_points']<=0||$numbers<0.01){
                    continue;
                }
                $city_lot = $numbers>$item['green_points']?$item['green_points']:$numbers;
                Db::name('member')->where(['id'=>$item['id']])->update([
                    'lot' => Db::raw('lot+' . $city_lot),
                    'green_points' => Db::raw('green_points-' . $city_lot)
                ]);
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>$city_lot,
                    'from_id'=>$member_id,
                    'type'=>26,
                    'info'=>'驿站代理人团队购物佣金',
                    'member_id'=>$item['id']
                ]);
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>-1*$city_lot,
                    'from_id'=>'',
                    'type'=>40,
                    'info'=>'驿站代理人团队购物佣金消耗',
                    'member_id'=>$item['id']
                ]);

            }
        }
    }
    /**
     * 购物增加个人及团队业绩
     * @param $member_id
     * @param $num
     * @return void
     * @throws \think\Exception
     */
    public function get_merits($member_id,$num){
        $memberInfo = Db::name('member')->where(['id'=>$member_id])->value('id_path');
        Db::name('member')->where(['id'=>$member_id])->setInc('merits',$num);
        if($memberInfo){
            //商业指数
            $idstr = substr($memberInfo,0,-1);
            Db::name('member')->where('id','in',$idstr)->setInc('group_merits',$num);
        }
    }
    /**
     * 顺顺福未中单福分返佣1-7级
     * @param $member_id 用户ID
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function machine_lot_rebate($member_id){
        $memberInfo = Db::name('member')->where(['id'=>$member_id])->value('id_path');
        //查询上7代
        $amount = config('machine_lot');
        $idstr = substr($memberInfo,0,-1);
        $member = Db::name('member')->field('id,green_points,depth,id_path,is_ship,tel')->where([['id','in',$idstr]])->limit(7)->order('depth desc')->select();
        if($member){
            $recordArr = [];
            foreach ($member as $key=>$value){
                //如果账户没有绿色福分 就跳过该账户
                if($value['green_points'] == 0 && $amount[$key] >0.01){
                    writeLog_s($value['id'],'绿色积分',$amount[$key],'顺顺福未中单福分返佣1-7级 绿色积分不足无法发放');
                    continue;
                }
                if($value['is_ship'] == 0 && $amount[$key] == 0.7){
                    writeLog_s($value['id'],'没有预约满10单',$amount[$key],'顺顺福未中单福分返佣1-7级 没有满10单预约 无法发放');
                    continue;
                }
                $num = $amount[$key]>$value['green_points']?$value['green_points']:$amount[$key];
                if($num<0.01){
                    continue;
                }
                $recordArr[] = [ //福分返佣记录记录
                    'number'=>$num,
                    'from_id'=>$member_id,
                    'type'=>21,
                    'info'=>'顺顺福未中单奖励',
                    'member_id'=>$value['id']
                ];
                $recordArr[] = [ //绿色积分扣除记录
                    'number'=>-1*$num,
                    'from_id'=>'',
                    'type'=>22,
                    'info'=>'顺顺福未中单奖励消耗',
                    'member_id'=>$value['id']
                ];
                Db::name('member')->where(['id'=>$value['id']])->update([
                    'lot' => Db::raw('lot+' . $num),
                    'green_points' => Db::raw('green_points-' . $num)
                ]);
            }
            Db::name('bal_record')->insertAll($recordArr);
        }

    }
    /**
     * 顺顺福未中单福分返佣8-12级
     * @param $member_id 用户ID
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function machine_lot_rebates($member_id){
        $memberInfo = Db::name('member')->field('id_path,depth')->where(['id'=>$member_id])->find();;
        //查询上7代
        $idstr = substr($memberInfo['id_path'],0,-1);
        if($memberInfo['depth']<=7){
            return false;
        }
        $depth = $memberInfo['depth'] - 7;
        $amount = config('machine_lots');
        $member = Db::name('member')->field('id,green_points,depth,id_path,is_ship,tel')->where([['id','in',$idstr],['depth','lt',$depth]])->limit(5)->order('depth desc')->select();
        if($member){
            $recordArr = [];
            foreach ($member as $key=>$value){
                //如果账户没有绿色福分 就跳过该账户
                if($value['green_points'] == 0 && $amount[$key] >0.01){
                    writeLog_s($value['id'],'绿色积分',$amount[$key],'顺顺福未中单福分返佣8-12级 绿色积分不足无法发放');
                    continue;
                }
                if($value['is_ship'] == 0 && $amount[$key] >0.01){
                    writeLog_s($value['id'],'没有预约满10单',$amount[$key],'顺顺福未中单福分返佣8-12级 没有满10单预约 无法发放');
                    continue;
                }
//                $num = 0.66>$value['green_points']?$value['green_points']:0.66;
                $num = $amount[$key]>$value['green_points']?$value['green_points']:$amount[$key];
                if($num<0.01){
                    continue;
                }
                $recordArr[] = [ //福分返佣记录记录
                    'number'=>$num,
                    'from_id'=>$member_id,
                    'type'=>21,
                    'info'=>'顺顺福未中单奖励',
                    'member_id'=>$value['id']
                ];
                $recordArr[] = [ //绿色积分扣除记录
                    'number'=>-1*$num,
                    'from_id'=>'',
                    'type'=>22,
                    'info'=>'顺顺福未中单奖励消耗',
                    'member_id'=>$value['id']
                ];
                Db::name('member')->where(['id'=>$value['id']])->update([
                    'lot' => Db::raw('lot+' . $num),
                    'green_points' => Db::raw('green_points-' . $num)
                ]);
            }
            Db::name('bal_record')->insertAll($recordArr);
        }

    }
    /**
     * 顺顺福中单福分返佣1-7级
     * @param $member_id 用户ID
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function machine_lot($member_id){
        $memberInfo = Db::name('member')->where(['id'=>$member_id])->value('id_path');
        //查询上7代
        $amount = config('machine_lot');
        $idstr = substr($memberInfo,0,-1);
        $member = Db::name('member')->field('id,green_points,depth,id_path,is_ship,tel')->where([['id','in',$idstr]])->limit(7)->order('depth desc')->select();
        if($member){
            $recordArr = [];
            foreach ($member as $key=>$value){
                //如果账户没有绿色福分 就跳过该账户
                if($value['green_points'] == 0 && $amount[$key] >0.01){
                    writeLog_s($value['id'],'绿色积分',$amount[$key],'顺顺福中单福分返佣1-7级 绿色积分不足无法发放');
                    continue;
                }
                if($value['is_ship'] == 0  && $amount[$key] == 0.7){
                    writeLog_s($value['id'],'没有预约满10单',$amount[$key],'顺顺福中单福分返佣1-7级 没有满10单预约 无法发放');
                    continue;
                }
                $num = $amount[$key]>$value['green_points']?$value['green_points']:$amount[$key];
                if($num<0.01){
                    continue;
                }
                $recordArr[] = [ //福分返佣记录记录
                    'number'=>$num,
                    'from_id'=>$member_id,
                    'type'=>38,
                    'info'=>'团队提货券每日收益奖励',
                    'member_id'=>$value['id']
                ];
                $recordArr[] = [ //绿色积分扣除记录
                    'number'=>-1*$num,
                    'from_id'=>'',
                    'type'=>39,
                    'info'=>'团队提货券每日收益奖励消耗',
                    'member_id'=>$value['id']
                ];
                Db::name('member')->where(['id'=>$value['id']])->update([
                    'lot' => Db::raw('lot+' . $num),
                    'green_points' => Db::raw('green_points-' . $num)
                ]);
            }
            Db::name('bal_record')->insertAll($recordArr);
        }

    }
    /**
     * 顺顺福中单福分返佣8-12级
     * @param $member_id 用户ID
     * @return void
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function machine_lots($member_id){
        $memberInfo = Db::name('member')->field('id_path,depth')->where(['id'=>$member_id])->find();;
        //查询上7代
        $idstr = substr($memberInfo['id_path'],0,-1);
        if($memberInfo['depth']<=7){
            return false;
        }
        $depth = $memberInfo['depth'] - 7;
        $amount = config('machine_lots');
        $member = Db::name('member')->field('id,green_points,depth,id_path,is_ship,tel')->where([['id','in',$idstr],['depth','lt',$depth]])->limit(5)->order('depth desc')->select();
        if($member){
            $recordArr = [];
            foreach ($member as $key=>$value){
                //如果账户没有绿色福分 就跳过该账户
                if($value['green_points'] == 0 && $amount[$key] >0.01){
                    writeLog_s($value['id'],'绿色积分',$amount[$key],'顺顺福中单福分返佣8-12级 绿色积分不足无法发放');
                    continue;
                }
                if($value['is_ship'] == 0  && $amount[$key] >0.01){
                    writeLog_s($value['id'],'没有预约满10单',$amount[$key],'顺顺福中单福分返佣8-12级 没有满10单预约 无法发放');
                    continue;
                }
                $num = $amount[$key]>$value['green_points']?$value['green_points']:$amount[$key];
                if($num<0.01){
                    continue;
                }
                $recordArr[] = [ //福分返佣记录记录
                    'number'=>$num,
                    'from_id'=>$member_id,
                    'type'=>38,
                    'info'=>'团队提货券每日收益奖励',
                    'member_id'=>$value['id']
                ];
                $recordArr[] = [ //绿色积分扣除记录
                    'number'=>-1*$num,
                    'from_id'=>'',
                    'type'=>39,
                    'info'=>'团队提货券每日收益奖励消耗',
                    'member_id'=>$value['id']
                ];
                Db::name('member')->where(['id'=>$value['id']])->update([
                    'lot' => Db::raw('lot+' . $num),
                    'green_points' => Db::raw('green_points-' . $num)
                ]);
            }
            Db::name('bal_record')->insertAll($recordArr);
        }

    }

    /**
     * 精选区购物返佣
     * @param $amount 订单总金额
     * @param $member_id 购买用户ID
     * @return void
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function choice_rebate($amount,$member_id){
        $parent_id = Db::name('member')->where(['id'=>$member_id])->value('parent_id');
        $green_points = round($amount*self::$config['green_points_ratio'],2);//绿色福分赠送数量
        $points = round($amount*self::$config['points_ratio'],2);//贡献积分赠送数量
        $lotNum = round($amount*self::$config['parent_ratio'],2);//推荐人赠送福分数量
        Db::name('bal_record')->insertAll([
            [ //贡献积分记录
                'number'=>$points,
                'from_id'=>'',
                'type'=>24,
                'info'=>'购物获得',
                'member_id'=>$member_id
            ],
            [ //绿色积分记录
                'number'=>$green_points,
                'from_id'=>'',
                'type'=>23,
                'info'=>'购物获得',
                'member_id'=>$member_id
            ]
        ]);
        Db::name('member')->where(['id'=>$member_id])->update([
            'green_points' => Db::raw('green_points+' . $green_points),
            'points' => Db::raw('points+' . $points)
        ]);
        if($parent_id){ //推荐人反佣 第一代
            $parGreen = Db::name('member')->where(['id'=>$parent_id])->value('green_points');
            $parday = Db::name('member')->where(['id'=>$parent_id])->value('day');
            if ($parday>0&&$parGreen>0&&$lotNum>0.001){
                $lot = $lotNum>$parGreen?$parGreen:$lotNum;
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>$lot,
                    'from_id'=>$member_id,
                    'type'=>25,
                    'info'=>'消费购物匹配获得',
                    'member_id'=>$parent_id
                ]);
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>-1*$lot,
                    'from_id'=>'',
                    'type'=>37,
                    'info'=>'消费购物匹配福分消耗',
                    'member_id'=>$parent_id
                ]);
                Db::name('member')->where(['id'=>$parent_id])->update([
                    'lot' => Db::raw('lot+' . $lot),
                    'green_points' => Db::raw('green_points-' . $lot)
                ]);
            }
            $parent_id2 = Db::name('member')->where(['id'=>$parent_id])->value('parent_id');
            $lotNum2 = round($amount*self::$config['parent_ratio2'],2);//二代推荐人赠送福分数量
            if($parent_id2){
                $this->choice_rebate_no2($parent_id2,$lotNum2,'二代',$member_id);
                $parent_id3 = Db::name('member')->where(['id'=>$parent_id2])->value('parent_id');
                $lotNum3 = round($amount*self::$config['parent_ratio3'],2);//三代推荐人赠送福分数量
                if($parent_id3){
                    $this->choice_rebate_no2($parent_id3,$lotNum3,'三代',$member_id);
                    $parent_id4 = Db::name('member')->where(['id'=>$parent_id3])->value('parent_id');
                    $lotNum4 = round($amount*self::$config['parent_ratio4'],2);//四代推荐人赠送福分数量
                    if($parent_id4){
                        $this->choice_rebate_no2($parent_id4,$lotNum4,'四代',$member_id);
                        $parent_id5 = Db::name('member')->where(['id'=>$parent_id4])->value('parent_id');
                        $lotNum5 = round($amount*self::$config['parent_ratio5'],2);//五代推荐人赠送福分数量
                        if($parent_id5){
                            $this->choice_rebate_no2($parent_id5,$lotNum5,'五代',$member_id);
                            $parent_id6 = Db::name('member')->where(['id'=>$parent_id5])->value('parent_id');
                            $lotNum6 = round($amount*self::$config['parent_ratio6'],2);//六代推荐人赠送福分数量
                            if($parent_id6){
                                $this->choice_rebate_no2($parent_id6,$lotNum6,'六代',$member_id);
                                $parent_id7 = Db::name('member')->where(['id'=>$parent_id6])->value('parent_id');
                                $lotNum7 = round($amount*self::$config['parent_ratio7'],2);//七代推荐人赠送福分数量
                                if($parent_id7){
                                    $this->choice_rebate_no2($parent_id7,$lotNum7,'七代',$member_id);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    //获取商品属于十二生肖配置中的哪个
    public function bronYearKey($cid){
        $value = 0;
        if($cid == 61){
            $value = 'shu';
        }
        if($cid == 62){
            $value = 'niu';
        }
        if($cid == 63){
            $value = 'hu';
        }
        if($cid == 64){
            $value = 'tu';
        }
        if($cid == 65){
            $value = 'long';
        }
        if($cid == 66){
            $value = 'she';
        }
        if($cid == 67){
            $value = 'ma';
        }
        if($cid == 68){
            $value = 'yang';
        }
        if($cid == 69){
            $value = 'hou';
        }
        if($cid == 70){
            $value = 'ji';
        }
        if($cid == 71){
            $value = 'gou';
        }
        if($cid == 72){
            $value = 'zhu';
        }
        return $value;
    }
    
    /**
     * 十二生肖返佣自己
     * @param $amount 订单总金额
     * @param $member_id 购买用户ID
     * @return void
     * @throws Exception
     * @throws \think\exception\PDOException
     */
      public function bronYearToSelf($amount,$member_id,$goods_id){
        $cid = Db::name('mall_dettype')->where(['pid'=>$goods_id])->value('cid');
        $key = $this->bronYearKey($cid);
        $bronYearRatio = Db::name('config')->where(['key'=>$key])->value('val');
        $points = round($amount*$bronYearRatio,2);//消费积分赠送数量
        Db::name('bal_record')->insertAll([
            [ //消费积分赠送记录
                'number'=>$points,
                'from_id'=>'',
                'type'=>42,
                'info'=>'购物获得',
                'member_id'=>$member_id
            ],
        ]);
        Db::name('member')->where(['id'=>$member_id])->update([
            'integral' => Db::raw('integral+' . $points)
        ]);
      }
    
     /**
     * 十二生肖返佣上级
     * @param $amount 订单总金额
     * @param $member_id 购买用户ID
     * @return void
     * @throws Exception
     * @throws \think\exception\PDOException
     */
     public function bronYear($amount,$member_id){
        $parent_id = Db::name('member')->where(['id'=>$member_id])->value('parent_id');
        $green_points = round($amount*self::$config['green_points_ratio'],2);//绿色福分赠送数量
        $points = round($amount*self::$config['points_ratio'],2);//贡献积分赠送数量
        $lotNum = round($amount*self::$config['parent_ratio'],2);//推荐人赠送福分数量
        Db::name('bal_record')->insertAll([
            [ //贡献积分记录
                'number'=>$points,
                'from_id'=>'',
                'type'=>24,
                'info'=>'购物获得',
                'member_id'=>$member_id
            ],
            [ //绿色积分记录
                'number'=>$green_points,
                'from_id'=>'',
                'type'=>23,
                'info'=>'购物获得',
                'member_id'=>$member_id
            ]
        ]);
        Db::name('member')->where(['id'=>$member_id])->update([
            'green_points' => Db::raw('green_points+' . $green_points),
            'points' => Db::raw('points+' . $points)
        ]);
        if($parent_id){ //推荐人反佣 第一代
            $parGreen = Db::name('member')->where(['id'=>$parent_id])->value('green_points');
            $parday = Db::name('member')->where(['id'=>$parent_id])->value('day');
            if ($parday>0&&$parGreen>0&&$lotNum>0.001){
                $lot = $lotNum>$parGreen?$parGreen:$lotNum;
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>$lot,
                    'from_id'=>$member_id,
                    'type'=>25,
                    'info'=>'消费购物匹配获得',
                    'member_id'=>$parent_id
                ]);
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>-1*$lot,
                    'from_id'=>'',
                    'type'=>37,
                    'info'=>'消费购物匹配福分消耗',
                    'member_id'=>$parent_id
                ]);
                Db::name('member')->where(['id'=>$parent_id])->update([
                    'lot' => Db::raw('lot+' . $lot),
                    'green_points' => Db::raw('green_points-' . $lot)
                ]);
            }
            $parent_id2 = Db::name('member')->where(['id'=>$parent_id])->value('parent_id');
            $lotNum2 = round($amount*self::$config['parent_ratio2'],2);//二代推荐人赠送福分数量
            if($parent_id2){
                $this->choice_rebate_no2($parent_id2,$lotNum2,'二代',$member_id);
            }
        }
    }
    
    
     /**
     * 会员B区(返佣直推两代)
     * @param $amount 订单总金额
     * @param $member_id 购买用户ID
     * @return void
     * @throws Exception
     * @throws \think\exception\PDOException
     */
     public function vipB($amount,$member_id){
        $parent_id = Db::name('member')->where(['id'=>$member_id])->value('parent_id');
        $lotNum = round($amount*self::$config['parent_ratio'],2);//推荐人赠送福分数量
        if($parent_id){ //推荐人反佣 第一代
            $parGreen = Db::name('member')->where(['id'=>$parent_id])->value('green_points');
            $parday = Db::name('member')->where(['id'=>$parent_id])->value('day');
            if ($parday>0&&$parGreen>0&&$lotNum>0.001){
                $lot = $lotNum>$parGreen?$parGreen:$lotNum;
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>$lot,
                    'from_id'=>$member_id,
                    'type'=>25,
                    'info'=>'消费购物匹配获得',
                    'member_id'=>$parent_id
                ]);
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>-1*$lot,
                    'from_id'=>'',
                    'type'=>37,
                    'info'=>'消费购物匹配福分消耗',
                    'member_id'=>$parent_id
                ]);
                Db::name('member')->where(['id'=>$parent_id])->update([
                    'lot' => Db::raw('lot+' . $lot),
                    'green_points' => Db::raw('green_points-' . $lot)
                ]);
            }
            $parent_id2 = Db::name('member')->where(['id'=>$parent_id])->value('parent_id');
            $lotNum2 = round($amount*self::$config['parent_ratio2'],2);//二代推荐人赠送福分数量
            if($parent_id2){
                $this->choice_rebate_no2($parent_id2,$lotNum2,'二代',$member_id);
            }
        }
    }
    
    public function choice_rebate_no2($parent_id,$lotNum,$key,$member_id){
        if($parent_id){
            $parGreen = Db::name('member')->where(['id'=>$parent_id])->value('green_points');
            $parday = Db::name('member')->where(['id'=>$parent_id])->value('day');
            if ($parday>0&&$parGreen>0&&$lotNum>0.001){
                $lot = $lotNum>$parGreen?$parGreen:$lotNum;
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>$lot,
                    'from_id'=>$member_id,
                    'type'=>25,
                    'info'=>'消费购物匹配获得',
                    'member_id'=>$parent_id
                ]);
                Db::name('bal_record')->insert([
                    //福分记录
                    'number'=>-1*$lot,
                    'from_id'=>'',
                    'type'=>37,
                    'info'=>'消费购物匹配获得福分消耗',
                    'member_id'=>$parent_id
                ]);
                Db::name('member')->where(['id'=>$parent_id])->update([
                    'lot' => Db::raw('lot+' . $lot),
                    'green_points' => Db::raw('green_points-' . $lot)
                ]);
            }
        }
        
    }

    /**
     * 会员区购物返佣
     * @param $member_id 购物用户ID
     * @return void
     */
    public function members_rebate($member_id){
        $green_points = self::$config['members_rebate'];//绿色福分赠送数量
        Db::name('bal_record')->insert([
            //绿色积分记录
                'number'=>$green_points,
                'type'=>23,
                'info'=>'购物获得',
                'member_id'=>$member_id
        ]);
        Db::name('member')->where(['id'=>$member_id])->setInc('green_points',$green_points);

    }

    /**
     * 普通区购物返佣
     * @param $amount
     * @param $member_id
     * @return void
     * @throws Exception
     */
    public function  common_rebate($amount,$member_id){
        $points = round($amount*0.03,2);//贡献积分赠送数量
        Db::name('bal_record')->insert([
            //绿色积分记录
            'number'=>$points,
            'type'=>24,
            'info'=>'购物获得',
            'member_id'=>$member_id
        ]);
        Db::name('member')->where(['id'=>$member_id])->setInc('points',$points);
    }
    /**
     * 积分区购物获得消费积分
     * @param $amount
     * @param $member_id
     * @return void
     * @throws Exception
     */
    public function  integral_rebate($amount,$member_id){
        $points = round($amount*0.9,2);//消费积分赠送数量
        Db::name('bal_record')->insert([
            //绿色积分记录
            'number'=>$points,
            'type'=>41,
            'info'=>'购物获得',
            'member_id'=>$member_id
        ]);
        Db::name('member')->where(['id'=>$member_id])->setInc('integral',$points);
    }


}