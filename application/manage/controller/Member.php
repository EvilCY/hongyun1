<?php
/**
 * 会员管理
 */
namespace app\manage\controller;
use AlibabaCloud\Cloudwf\V20170328\DelUmengPagePermission4Root;
use alipay\aop\AopCertClient;
use alipay\aop\AopClient;
use alipay\aop\request\AlipayFundTransAppPayRequest;
use alipay\aop\request\AlipayFundTransToaccountTransferRequest;
use alipay\aop\request\AlipayFundTransUniTransferRequest;
use app\index\controller\Push;
use app\lib\AliSms;
use app\lib\Csv;
use app\lib\GoogleAuthenticator;
use app\lib\YunZhong;
use library\Controller;
use think\Db;
use think\Exception;

class Member extends Controller
{
    protected static $config;
    public function __construct()
    {
        parent::__construct();
        self::$config = $this->setConfig();
    }
    private function setConfig()
    {
        return Db::name('config')->cache('web_conf',600)->column('val','key');
    }
    public function recharge_record(){
        $where = [];
        
        //$this->error(input('pay_status'));
        
        if(input('tel')) {
            $where[] = ['m.tel','like','%'.input('tel').'%'];
        }
        if(input('pay_type')) {
            $where[] = ['p.pay_type','eq',input('pay_type')];
        }
        if(strlen(input('pay_status')) !== 0) {
            //$this->error(input('pay_status'));
            $where[] = ['p.status','eq',input('pay_status')];
        }else{
            $where[] = ['p.status','eq',1];
        }
        
        $time = input('get.add_time');
        if(isset($time) && $time){
            $aa = explode(' - ',$time);
            $where[] = ['p.add_time','between',[$aa[0],$aa[1]]];
        }
        if(input('group_tel')) {
            $group_tel = input('group_tel');
            $member_id = Db::name('member')->where(['tel'=>$group_tel])->value('id');
            if(!$member_id){
                $this->error('查无此人');
            }
            $isd = Db::name('member')->where("FIND_IN_SET({$member_id},id_path)")->column('id');
            $where[] = ['p.member_id','in',$isd];
        }
        $num = Db::name("prestore_recharge p")->join('member m','m.id=p.member_id')->where($where)->sum('amount');
        $this->assign([
            'num'=>$num
        ]);
        $this->_query("prestore_recharge p")->field('p.*,m.tel')->join('member m','m.id=p.member_id')->where($where)->order('p.recharge_id desc')->page();
    }
    public function index(){
        $this->title = '会员列表';
        $where = [];
        if(input('account')) {
            $where[] = ['m.tel','like','%'.input('account').'%'];
        }
        if(input('id')) {
            $where[] = ['m.id','eq',input('id')];
        }
        if(input('is_lock')!= '') {
            $where[] = ['m.is_lock','eq',input('is_lock')];
        }
        if(input('vip_level') != '') {
            $where[] = ['m.level','eq',input('vip_level')];
        }
        if(input('is_vip') != '') {
            $where[] = ['m.is_vip','eq',input('is_vip')];
        }
        if(input('city_agency') != '') {
            $where[] = ['m.city_agency','eq',input('city_agency')];
        }
        if(input('station_agent') != '') {
            $where[] = ['m.station_agent','eq',input('station_agent')];
        }
        if(input('is_card') != '') {
            $users = Db::name('member_yunzhong')->column('member_id');
            if(input('is_card') == 1){
                $where[] = ['m.id','in',$users];
            }else if(input('is_card') == 2){
                $where[] = ['m.id','notin',$users];
            }else{
                $users = Db::name('member_yunzhong')->where(['status'=>0])->column('member_id');
                $where[] = ['m.id','in',$users];
            }
            $where[] = ['m.station_agent','eq',input('station_agent')];
        }
        $config_ids_str = Db::name('config')->where(['id' => '43'])->value('val');
        // 将字符串转换为数组
        $config_ids = explode(',', $config_ids_str);
        // 将数组传递给模板
        $this->assign('config_ids', $config_ids);
        
        $this->_query('member m')->field('m.*,m2.tel as ptel')->join('member m2','m.parent_id=m2.id','left')->where($where)->order('m.id desc')->page();
        $this->_query('member')->where($where)->order('id desc')->page();
    }
    public function member_lock(){
        if(request()->isGet()){
            return $this->fetch();
        }else{
            $id = input('id');
            $is_lock = input('is_lock');
            $remark = input('lock_remark');
            $update['is_lock'] = $is_lock;
            if($is_lock==1) {
                if (!$remark) {
                    $this->error('请填写备注');
                }
                $update['lock_remark'] = $remark.'：后台注销于' .date('Y-m-d H:i:s');
            }
            Db::name('member')->where(['id'=>$id])->update($update);
            $this->success('操作成功');
        }
    }
    public function machine_lock(){
        if(request()->isPost()){
            $code = input('post.code');
            $ver_res = $this->verifyCode(13505740024,$code,'machine_lock');
            if(!$ver_res['status']){
                $this->error($ver_res['msg']);
            }
            Db::startTrans();
            try {
                Db::name('machine_pick')->where([['status','eq',1],['is_lock','eq',0]])->update([
                    'is_lock'=>3
                ]);
                $todayMemberId = Db::name('machine_order')->where("status = 1 and create_time>'".date('Y-m-d 00:00:00')."'")->group('member_id')->column('member_id');  //当日参加的用户ID
                if($todayMemberId){
                    $cutMemberId = '';
                    $cutMemberArr = [];
                    foreach ($todayMemberId as $user_id){
                        $cutMemberId .= $user_id.',';
                        $cutMemberArr[] = $user_id;
                    }
                    $idstr = substr($cutMemberId,0,-1);
                    $day30 = Db::name('member')->field('id')->where("id in({$idstr}) and day<=30")->column('id');//筛选出30天内的中单ID
                    $machinePick = $machineClose = [];
                    if($day30){
                        foreach ($day30 as $member_id){
                            $machineOrder = Db::name('machine_order')->where("member_id = $member_id and status = 1 and create_time>'".date('Y-m-d 00:00:00')."'")->select();
                            foreach ($machineOrder as $item){
                                $machineClose[] = [
                                    'machine_id'=>$item['machine_id'],
                                    'member_id'=>$item['member_id'],
                                    'price'=>$item['price'],
                                    'logo'=>self::$config['close_img'],
                                    'income'=>$item['income'],
                                    'status'=>1,
                                    'ex_time'=>333,
                                    'active_time'=>date('Y-m-d H:i:s'),
                                    'order_sn'=>$item['order_sn'],
                                    'machine_time'=>date('Y-m-d H:i:s')
                                ];
                            }
                        }
                        Db::name('machine_close')->insertAll($machineClose);
                        if(count($day30) == count($cutMemberArr)){
                            $finalArr = [];
                        }else{
                            $finalArr = array_diff($cutMemberArr,$day30);
                        }
                    }else{
                        $finalArr = $cutMemberArr;
                    }
                    if($finalArr){
                        foreach ($finalArr as $value){
                            $machineOrder = Db::name('machine_order')->where("member_id = $value and status = 1 and create_time>'".date('Y-m-d 00:00:00')."'")->select();
                            foreach ($machineOrder as $item){
                                $machinePick[] = [
                                    'machine_id'=>$item['machine_id'],
                                    'member_id'=>$value,
                                    'price'=>$item['price'],
                                    'logo'=>self::$config['pick_img'],
                                    'lot'=>self::$config['lot'],
                                    'freeze_lot'=>self::$config['freeze_lot'],
                                    'status'=>1,
                                    'is_lock'=>3,
                                    'ex_time'=>100,
                                    'active_time'=>date('Y-m-d H:i:s'),
                                    'order_sn'=>$item['order_sn'],
                                    'machine_time'=>date('Y-m-d H:i:s')
                                ];

                            }
                        }
                        Db::name('machine_pick')->insertAll($machinePick);
                    }
                }
                Db::name('machine_order')->where("status = 1 and create_time>'".date('Y-m-d 00:00:00')."'")->update([
                    'status'=>3,
                    'active_time'=>date('Y-m-d H:i:s')
                ]);
                Db::commit();
                $this->success('操作成功');
            }catch (Exception $exception){
                Db::rollback();
                $this->error('失败，请稍后再试'.$exception->getMessage());
            }
        }else{
            $this->fetch();
        }

    }
    /**
    * 验证码验证是否正确
    * @param $tell
    * @param $code
    * @return
    */
    protected final function verifyCode($tell,$code,$flag=''){
        if(!preg_match('/^\d{6}$/',$code)){
            return [
                'status' => false,
                'msg' => '验证码有误'
            ];
        }
        $sessionKey = "verification_".$flag.$tell;
        $verification = cache($sessionKey);
        if (!$verification) {
            return [
                'status' => false,
                'msg' => '请先获取验证码'
            ];
        }
        if ($tell != $verification['tel']){
            return [
                'status' => false,
                'msg' => '验证码有误',
            ];
        }
        $auth_code = md5($code);//验证码
        if ($verification['verificat'] != $auth_code){
            return [
                'status' => false,
                'msg' => '验证码不正确'
            ];
        }
        if ($verification['expirat_time'] < time()) {
            return [
                'status' => false,
                'msg' => '请重新获取验证码'
            ];
        }
        return [
            'status' => true,
            'msg' => 'success'
        ];
    }
    public function send_lock_verify(){
        $tel = 13505740024;
        $sessionKey = "verification_machine_lock".$tel;
        $randInt = create_rand_str(6, 2);
        $verCodeAging = 300;
//        cache($sessionKey,[
//            'tel' => $tel,
//            'expirat_time' => time() + $verCodeAging,
//            'verificat' => md5(868588),
//            'send_time' => time()],60);
//        return ['status'=>true,'msg'=>'发送成功'];
        $ali_sms = config('ali_sms');
        $cl = new AliSms($ali_sms['account'], $ali_sms['password'],$ali_sms['signname'],$ali_sms['tcode']);
        $result = $cl->sendSMS($tel, $randInt);

        if ($result) {
            #发送成功
            cache($sessionKey,[
                'tel' => $tel,
                'expirat_time' => time() + $verCodeAging,
                'verificat' => md5($randInt),
                'send_time' => time()],300);
            return ['status'=>true,'msg'=>'success'];
        }
        return ['status'=>false,'msg'=>'验证码发送失败,请稍后再试'];
    }
    
    //重置登录密码
    
    public function send_miss_psw(){
        if(request()->isPost()){
            $tel = 13646615482;
            if ($tel != input('content')){
                return [
                    'status' => false,
                    'msg' => '接收密码的手机号错误！'
                ];
            }
        
        $randInt = mt_rand(100000, 999999); // 生成一个长度为6的随机字符串，包含英文字母、数字
        $ali_sms = config('ali_sms');
        $cl = new AliSms($ali_sms['account'], $ali_sms['password'],$ali_sms['signname'],$ali_sms['tcode']);
        $result = $cl->sendSMS($tel, $randInt);

        if ($result) {
            //修改登录密码
            $id = input('id');
            Db::name('member')->where(['id' => $id])->update(['login_pwd' => md5($randInt)]);    
                
            return ['status'=>true,'msg'=>'登录密码修改成功！'];
        }
        return ['status'=>false,'msg'=>$result];
        }
    }
    
    /**
     * 平台资质
     * @return array|void
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function natural(){
        if(request()->isPost()){
            $data = input('post.detail');
            if(!$data){
                return[
                    'code' => 0,
                    'info' => '请添加介绍',
                ];
            }
            Db::name('hy_config')->where(['key'=>'natural'])->update([
                'val'=>$data
            ]);
            return [
                'code' => 1,
                'info' => '编辑成功'
            ];
        }else{
            $this->title = '平台资质';
            $detail = Db::name('hy_config')->where(['key'=>'natural'])->value('val');
            $this->assign('detail',$detail);
            $this->fetch();
        }
    }
    /**
     * 平台资质
     * @return array|void
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    public function vip_member(){
        if(request()->isPost()){
            $data = input('post.detail');
            if(!$data){
                return[
                    'code' => 0,
                    'info' => '请添加介绍',
                ];
            }
            Db::name('hy_config')->where(['key'=>'vip_member'])->update([
                'val'=>$data
            ]);
            return [
                'code' => 1,
                'info' => '编辑成功'
            ];
        }else{
            $this->title = '平台资质';
            $detail = Db::name('hy_config')->where(['key'=>'vip_member'])->value('val');
            $this->assign('detail',$detail);
            $this->fetch();
        }
    }
    public function member_level(){
        if(request()->isGet()){
            return $this->fetch();
        }else{
            $tel = input('post.tel');
            $vip_level = input('post.vip_level');
            $provid = input('post.provid');
            $cityid = input('post.cityid');
            $areaid = input('post.areaid');
            $member_info = Db::name('member')->field('id,level')->where(['tel'=>$tel])->find();
            $member_level = config('vip_level');
            $msg = $member_level[$vip_level];
            if(!$member_info){
                return [
                    'code' => 0,
                    'info' => '用户不存在',
                ];
            }
            if($member_info['level']==$vip_level){
                $this->error('该用户已经是'.$msg);
            }
            Db::name('member')->where(['tel'=>$tel])->update([
                'level'=>$vip_level,
                'province'=>$provid,
                'city'=>$cityid,
                'area'=>$areaid,
            ]);
            Db::name('vip_log')->insert([
                'member_id'=>$member_info['id'],
                'type'=>3,
                'vip_level'=>$vip_level
            ]);
            return [
                'code' => 1,
                'info' => '操作成功',
            ];
        }
    }

    /**
     * @return void 团队业绩
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function member_team(){
        $user_id = input('user_id');
        $tel = input('tel');
        $info = Db::name('member')->field('group_num,tel,id,(group_merits+merits) as group_merits')->where(['id'=>$user_id])->find();
        $where[] = ['parent_id','eq',$info['id']];
        if($tel){
            $where[] = ['tel','like','%'.$tel.'%'];
        }
        $this->assign([
            'info'=>$info
        ]);
        $this->_query('member')->field('id,tel,level,group_merits,merits,is_lock,lock_remark,create_time')->where($where)->page();
    }
    static function member_tree($user_id){
        $info = Db::name('member')->field('id,tel,life_vip')->where(['id'=>$user_id])->find();
        $vip_level = config('vip_level');
        $data['name'] = '【'.$info['id'].'】'.$vip_level[$info['life_vip']];
        $data['title'] = $info['tel'];
        $children = Db::name('member')->where(['parent_id'=>$user_id])->column('id');
        if(!$children){
            return $data;
        }
        $tmp = [];
        foreach ($children as $id){
            $info = Db::name('member')->field('id,tel,life_vip')->where(['id'=>$id])->find();
            $vip_level = config('vip_level');
            $data['name'] = '【'.$info['id'].'】'.$vip_level[$info['life_vip']];
            $data['title'] = $info['tel'];
            $tmp = $data;;
        }
        $data['children'][] = $tmp;
        return $data;
    }
    
    /*
    public function member_account(){
        $user_id = input('get.user_id');
        $cointype = input('get.cointype',1,'intval');
        $info = Db::name('member')->where(['id'=>$user_id])->find();
        $this->assign('coin_type',$cointype);
        $this->assign('info',$info);
        if($cointype == 1){
            $arry = config('integral_type');
        }elseif ($cointype == 2){
            $arry = config('points_type');
        }elseif($cointype == 3){
            $arry = config('green_points');
        }elseif ($cointype == 4){
            $arry = config('lot_type');
        }else{
            $arry = config('freeze_lot');
        }

        $this->_query('bal_record b')->field('b.from_id as from_id,b.number as num,b.info as `desc`,b.id,b.add_time,b.member_id as tel')->cache(600)->where([['member_id','eq',$user_id],['type','in',$arry]])->order('id desc')->page(); //2024-03-18
        
        //$this->_query('bal_record b')->field('b.number as num,b.info as `desc`,b.id,b.add_time,m.tel')->cache(600)->leftJoin('member m','m.id=b.from_id')->where([['member_id','eq',$user_id],['type','in',$arry]])->order('id desc')->page(); //2024-03-18

    }
    */
    
    public function member_account(){
        $user_id = input('get.user_id');
        $cointype = input('get.cointype',1,'intval');
        $db_type = input('get.db_type',1);//1-最新数据 2-历史数据
        $info = Db::name('member')->where(['id'=>$user_id])->find();
        $this->assign('coin_type',$cointype);
        $this->assign('db_type',$db_type);
        $this->assign('info',$info);
        if($cointype == 1){
            $arry = config('integral_type');
            $db = 'bal_record_integral_history b';
        }elseif ($cointype == 2){
            $arry = config('points_type');
            $db = 'bal_record_points_history b';
        }elseif($cointype == 3){
            $arry = config('green_points');
            $db = 'bal_record_green_history b';
        }elseif ($cointype == 4){
            $arry = config('lot_type');
            $db = 'bal_record_lot_history b';
        }else{
            $arry = config('freeze_lot');
            $db = 'bal_record_freeze_history b';
        }

        if($db_type == 1){
            $this->_query('bal_record b')
                ->field('b.number as num,b.info as `desc`,b.id,b.add_time as create_time,m.tel')
                ->leftJoin('member m','m.id=b.from_id')
                ->where([['member_id','eq',$user_id],['type','in',$arry]])->cache(60)
                ->order('id desc')->page();
        }else{
            $this->_query($db)
                ->field('b.number as num,b.info as `desc`,b.id,b.add_time as create_time,m.tel')
                ->leftJoin('member m','m.id=b.from_id')
                ->where([['member_id','eq',$user_id],['type','in',$arry]])->cache(60)
                ->order('id desc')->page();
        }
    }
    
    public function data_center(){
        $this->title = '数据中心';
        $type = input('get.type');
        $time = input('get.end_time');
        
        if (!isset($type)) {$type = '0';}
                
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
                // 今天到明天
                $data = [$today,$tomorrow];
                //$time = $today.' - '.$tomorrow;
                break;  
            case 1:  
                // 昨天到今天  
                $data = [$yesterday, $today];  
                break;  
            case 2:  
                // 7天前到昨天  
                $data = [$sevenDaysAgo, $yesterday];  
                break;  
            case 3:  
                // 30天前到昨天  
                $data = [$thirtyDaysAgo, $yesterday];  
                break;  
            case 4:  
                // 本月1号到昨天  
                $data = [$firstDayOfMonth, $yesterday];  
                break;  
            case 5:  
                // 上个月1号到本月1号 
                $data = [$lastMonthFirstDay, $firstDayOfMonth];  
                break;      
            case 6:  
                // 本年1号到昨天  
                $data = [$firstDayOfYear, $yesterday];  
                break;  
            case 7:
                $data = ['2023-01-01', $yesterday];
                break;
                //全部历史到昨天
            default:  
                //默认值
                $data = ['2023-01-01', $yesterday];
                break;  
        }  
        
        if(isset($time) && $time){
                $aa = explode(' - ',$time);
                $aa[1] = date("Y-m-d",strtotime("+1 day",strtotime($aa[1])));
                $data = [$aa[0],$aa[1]];
                //如果有日期则跳过缓存
                
                //总计充值金额
                $rechargeBal = Db::name('prestore_recharge')->where([['pay_time','between',$data]])->sum('amount');
                //消费积分
                /*
                $integral = Db::name('bal_record')->where([['type','in',config('integral_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
                */
                $integral_main = Db::name('bal_record')->where([['type','in',config('integral_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
                $integral_history = Db::name('bal_record_integral_history')->where([['number','gt',0],['add_time','between',$data]])->sum('number');
                $integral = $integral_main + $integral_history;
                //贡献积分
                /*
                $points = Db::name('bal_record')->where([['type','in',config('points_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
                */
                $points_main = Db::name('bal_record')->where([['type','in',config('points_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
                $points_history = Db::name('bal_record_points_history')->where([['number','gt',0],['add_time','between',$data]])->sum('number');
                $points = $points_main + $points_history;
                //福分
                /*
                $lot = Db::name('bal_record')->where([['type','in',config('lot_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
                */
                $lot_main = Db::name('bal_record')->where([['type','in',config('lot_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
                $lot_history = Db::name('bal_record_lot_history')->where([['number','gt',0],['add_time','between',$data]])->sum('number');
                $lot = $lot_main + $lot_history;
                //冻结福分
                /*
                $freeze_lot = Db::name('bal_record')->where([['type','in',config('freeze_lot')],['number','gt',0],['add_time','between',$data]])->sum('number');
                */
                $freeze_main = Db::name('bal_record')->where([['type','in',config('freeze_lot')],['number','gt',0],['add_time','between',$data]])->sum('number');
                $freeze_history = Db::name('bal_record_freeze_history')->where([['number','gt',0],['add_time','between',$data]])->sum('number');
                $freeze_lot = $freeze_main + $freeze_history;
                //绿色积分
                /*
                $green_points = Db::name('bal_record')->where([['type','in',config('green_points')],['number','gt',0],['add_time','between',$data]])->sum('number');
                */
                $green_points_main = Db::name('bal_record')->where([['type','in',config('green_points')],['number','gt',0],['add_time','between',$data]])->sum('number');
                $green_points_history = Db::name('bal_record_green_history')->where([['number','gt',0],['add_time','between',$data]])->sum('number');
                $green_points = $green_points_main + $green_points_history;
                //提现
                $withdraw = Db::name('commission_withdraw')->where([['create_time','between',$data],['status','in',[0,1,2,3,4]]])->sum('money');
                //新增会员
                $newMember = Db::name('member')->where([['create_time','between',$data]])->count('id');
                //体验
                $Member = Db::name('member')->where([['create_time','between',$data],['is_vip','eq',0]])->count('id');
                //正式
                $vipMember = Db::name('mall_order')->where([['pay_time','between',$data],['goods_type','eq',5]])->count('order_id');
                //城市代理
                $city_agency = Db::name('vip_log')->where([['create_time','between',$data],['type','eq',1]])->count('id');
                //驿站代理
                $station_agent = Db::name('vip_log')->where([['create_time','between',$data],['type','eq',2]])->count('id');
                //行政区代理
                $v1 = Db::name('vip_log')->where([['create_time','between',$data],['type','eq',3],['vip_level','eq',1]])->count('id');
                $v2 = Db::name('vip_log')->where([['create_time','between',$data],['type','eq',3],['vip_level','eq',2]])->count('id');
                //预约中单
                $machineOrder = Db::name('machine_order')->where([['pay_time','between',$data],['status','eq',3]])->count('id');
                //中单金额
                $machineBal = Db::name('machine_order')->where([['pay_time','between',$data],['status','eq',3]])->sum('price');
                //总预约单量
                $Order = Db::name('machine_order')->where([['pay_time','between',$data]])->count('id');
                //总预约额
                $OrderBal = Db::name('machine_order')->where([['pay_time','between',$data]])->sum('price');
                //中单发放福分
                $order_lot = Db::name('bal_record')->where([['type','in',[27]],['number','gt',0],['add_time','between',$data]])->sum('number');
                //未中单
                $orderNo = Db::name('machine_order')->where([['pay_time','between',$data],['status','in',[1,2]]])->count('id');
                //未中单发放福分
                $sendLot = Db::name('bal_record')->where([['type','in',[20]],['number','gt',0],['add_time','between',$data]])->sum('number');
                //商城成单
                $mallOrder = Db::name('mall_order')->where([['add_time','between',$data],['order_status','in',[1,2,3]]])->count('order_id');
                //成交额
                $mallBal = Db::name('mall_order')->where([['add_time','between',$data],['order_status','in',[1,2,3]]])->sum('order_amount');
                //兑换额
                $exchange = Db::name('mall_order')->where([['add_time','between',$data],['order_status','in',[1,2,3]],['goods_type','eq',3]])->sum('order_amount');
                //养生专区商品
                $yangsheng = Db::name('mall_order o')->cache(600)->leftJoin('mall_product g','g.id = o.goods_id')->leftJoin('mall_dettype d','d.pid = g.id')->where([['o.add_time','between',$data],['o.order_status','in',[1,2,3]],['d.prid','eq',8]])->sum('o.order_amount');
                $jiayong = Db::name('mall_order o')->cache(600)->leftJoin('mall_product g','g.id = o.goods_id')->leftJoin('mall_dettype d','d.pid = g.id')->where([['o.add_time','between',$data],['o.order_status','in',[1,2,3]],['d.prid','eq',11]])->sum('o.order_amount');
                $nongte = Db::name('mall_order o')->cache(600)->leftJoin('mall_product g','g.id =o.goods_id')->leftJoin('mall_dettype d','d.pid = g.id')->where([['o.add_time','between',$data],['o.order_status','in',[1,2,3]],['d.prid','eq',16]])->sum('o.order_amount');
                //健康大使
                $totalNum = Db::name('mall_weight_list')->where([['create_time','between',$data]])->count('id');
                $weight_list = Db::name('mall_weight m')->where([['w.create_time','between',$data]])->field('m.name,count(w.id) as num')->leftJoin('mall_weight_list w','m.id = w.weight_id')->group('m.id')->select();
        }else{
                //总计充值金额
                $rechargeBal = cache('$rechargeBal'.$type);
                //消费积分
                $integral = cache('$integral'.$type);
                //贡献积分
                $points = cache('$points'.$type);
                //福分
                $lot = cache('$lot'.$type);
                //冻结福分
                $freeze_lot = cache('$freeze_lot'.$type);
                //绿色积分
                $green_points = cache('$green_points'.$type);
                //提现
                $withdraw = cache('$withdraw'.$type);
                //新增会员
                $newMember = cache('$newMember'.$type);
                //体验
                $Member = cache('$Member'.$type);
                //正式
                $vipMember = cache('$vipMember'.$type);
                //城市代理
                $city_agency = cache('$city_agency'.$type);
                //驿站代理
                $station_agent = cache('$station_agent'.$type);
                //行政区代理
                $v1 = cache('$v1'.$type);
                $v2 = cache('$v2'.$type);
                //预约中单
                $machineOrder = cache('$machineOrder'.$type);
                //中单金额
                $machineBal = cache('$machineBal'.$type);
                //总预约单量
                $Order = cache('$Order'.$type);
                //总预约额
                $OrderBal = cache('$OrderBal'.$type);
                //中单发放福分
                $order_lot = cache('$order_lot'.$type);
                //未中单
                $orderNo = cache('$orderNo'.$type);
                //未中单发放福分
                $sendLot = cache('$sendLot'.$type);
                //商城成单
                $mallOrder = cache('$mallOrder'.$type);
                //成交额
                $mallBal = cache('$mallBal'.$type);
                //兑换额
                $exchange = cache('$exchange'.$type);
                //养生专区商品
                $yangsheng = cache('$yangsheng'.$type);
                $jiayong = cache('$jiayong'.$type);
                $nongte = cache('$nongte'.$type);
                //健康大使
                $totalNum = cache('$totalNum'.$type);
                $weight_list = cache('$weight_list'.$type);
            }
                $this->assign([
                    'rechargeBal'=>$rechargeBal,
                    'integral'=>$integral,
                    'points'=>$points,
                    'lot'=>$lot,
                    'freeze_lot'=>$freeze_lot,
                    'green_points'=>$green_points,
                    'withdraw'=>$withdraw,
                    'newMember'=>$newMember,
                    'Member'=>$Member,
                    'vipMember'=>$vipMember,
                    'city_agency'=>$city_agency,
                    'station_agent'=>$station_agent,
                    'v1'=>$v1,
                    'v2'=>$v2,
                    'machineOrder'=>$machineOrder,
                    'machineBal'=>$machineBal,
                    'Order'=>$Order,
                    'OrderBal'=>$OrderBal,
                    'order_lot'=>$order_lot,
                    'orderNo'=>$orderNo,
                    'sendLot'=>$sendLot,
                    'mallOrder'=>$mallOrder,
                    'mallBal'=>$mallBal,
                    'exchange'=>$exchange,
                    'yangsheng'=>$yangsheng,
                    'jiayong'=>$jiayong,
                    'nongte'=>$nongte,
                    'totalNum'=>$totalNum,
                    'weight_list'=>$weight_list,
                ]);
                $this->fetch();
    }
    

    public function export_data_center(){
        $type = input('get.type');
        $time = input('get.end_time');
        
        if (!isset($type)) {$type = '1';}
                
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
                // 今天到明天 
                $data = [$today,$tomorrow];
                break;  
            case 1:  
                // 昨天到今天  
                $data = [$yesterday, $today];  
                break;  
            case 2:  
                // 7天前到昨天  
                $data = [$sevenDaysAgo, $yesterday];  
                break;  
            case 3:  
                // 30天前到昨天  
                $data = [$thirtyDaysAgo, $yesterday];  
                break;  
            case 4:  
                // 本月1号到昨天  
                $data = [$firstDayOfMonth, $yesterday];  
                break;  
            case 5:  
                // 上个月1号到本月1号 
                $data = [$lastMonthFirstDay, $firstDayOfMonth];  
                break;      
            case 6:  
                // 本年1号到昨天  
                $data = [$firstDayOfYear, $yesterday];  
                break;  
            case 7:
                $data = ['2023-01-01', $yesterday];
                break;
                //全部历史到昨天
            default:  
                //默认值
                $data = ['2023-01-01', $yesterday];
                break;  
        }          
        
        if(isset($time) && $time){
            $aa = explode(' - ',$time);
            $aa[1] = date("Y-m-d",strtotime("+1 day",strtotime($aa[1])));
            $data = [$aa[0],$aa[1]];
        }
        //总计充值金额
        $rechargeBal = Db::name('prestore_recharge')->where([['pay_time','between',$data]])->sum('amount');
        //消费积分
        /*
        $integral = Db::name('bal_record')->where([['type','in',config('integral_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
        */
        $integral_main = Db::name('bal_record')->where([['type','in',config('integral_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
        $integral_history = Db::name('bal_record_integral_history')->where([['number','gt',0],['add_time','between',$data]])->sum('number');
        $integral = $integral_main + $integral_history;
        //贡献积分
        /*
        $points = Db::name('bal_record')->where([['type','in',config('points_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
        */
        $points_main = Db::name('bal_record')->where([['type','in',config('points_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
        $points_history = Db::name('bal_record_points_history')->where([['number','gt',0],['add_time','between',$data]])->sum('number');
        $points = $points_main + $points_history;
        //福分
        /*
        $lot = Db::name('bal_record')->where([['type','in',config('lot_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
        */
        $lot_main = Db::name('bal_record')->where([['type','in',config('lot_type')],['number','gt',0],['add_time','between',$data]])->sum('number');
        $lot_history = Db::name('bal_record_lot_history')->where([['number','gt',0],['add_time','between',$data]])->sum('number');
        $lot = $lot_main + $lot_history;
        //冻结福分
        /*
        $freeze_lot = Db::name('bal_record')->where([['type','in',config('freeze_lot')],['number','gt',0],['add_time','between',$data]])->sum('number');
        */
        $freeze_main = Db::name('bal_record')->where([['type','in',config('freeze_lot')],['number','gt',0],['add_time','between',$data]])->sum('number');
        $freeze_history = Db::name('bal_record_freeze_history')->where([['number','gt',0],['add_time','between',$data]])->sum('number');
        $freeze_lot = $freeze_main + $freeze_history;
        //绿色积分
        /*
        $green_points = Db::name('bal_record')->where([['type','in',config('green_points')],['number','gt',0],['add_time','between',$data]])->sum('number');
        */
        $green_points_main = Db::name('bal_record')->where([['type','in',config('green_points')],['number','gt',0],['add_time','between',$data]])->sum('number');
        $green_points_history = Db::name('bal_record_green_history')->where([['number','gt',0],['add_time','between',$data]])->sum('number');
        $green_points = $green_points_main + $green_points_history;
        //提现
        $withdraw = Db::name('commission_withdraw')->where([['create_time','between',$data],['status','in',[0,1,2,3,4]]])->sum('money');
        //新增会员
        $newMember = Db::name('member')->where([['create_time','between',$data]])->count('id');
        //体验
        $Member = Db::name('member')->where([['create_time','between',$data],['is_vip','eq',0]])->count('id');
        //正式
        $vipMember = Db::name('mall_order')->where([['pay_time','between',$data],['goods_type','eq',5]])->count('order_id');
        //城市代理
        $city_agency = Db::name('vip_log')->where([['create_time','between',$data],['type','eq',1]])->count('id');
        //驿站代理
        $station_agent = Db::name('vip_log')->where([['create_time','between',$data],['type','eq',2]])->count('id');
        //行政区代理
        $v1 = Db::name('vip_log')->where([['create_time','between',$data],['type','eq',3],['vip_level','eq',1]])->count('id');
        $v2 = Db::name('vip_log')->where([['create_time','between',$data],['type','eq',3],['vip_level','eq',2]])->count('id');
        //预约中单
        $machineOrder = Db::name('machine_order')->where([['pay_time','between',$data],['status','eq',3]])->count('id');
        //中单金额
        $machineBal = Db::name('machine_order')->where([['pay_time','between',$data],['status','eq',3]])->sum('price');
        //总预约单量
        $Order = Db::name('machine_order')->where([['pay_time','between',$data]])->count('id');
        //总预约额
        $OrderBal = Db::name('machine_order')->where([['pay_time','between',$data]])->sum('price');
        //中单发放福分
        $order_lot = Db::name('bal_record')->where([['type','in',[27]],['number','gt',0],['add_time','between',$data]])->sum('number');
        //未中单
        $orderNo = Db::name('machine_order')->where([['pay_time','between',$data],['status','in',[1,2]]])->count('id');
        //未中单发放福分
        $sendLot = Db::name('bal_record')->where([['type','in',[20]],['number','gt',0],['add_time','between',$data]])->sum('number');
        //商城成单
        $mallOrder = Db::name('mall_order')->where([['add_time','between',$data],['order_status','in',[1,2,3]]])->count('order_id');
        //成交额
        $mallBal = Db::name('mall_order')->where([['add_time','between',$data],['order_status','in',[1,2,3]]])->sum('order_amount');
        //兑换额
        $exchange = Db::name('mall_order')->where([['add_time','between',$data],['order_status','in',[1,2,3]],['goods_type','eq',3]])->sum('order_amount');
        //养生专区商品
        $yangsheng = Db::name('mall_order o')->cache(600)->leftJoin('mall_product g','g.id = o.goods_id')->leftJoin('mall_dettype d','d.pid = g.id')->where([['o.add_time','between',$data],['o.order_status','in',[1,2,3]],['d.prid','eq',8]])->sum('o.order_amount');
        $jiayong = Db::name('mall_order o')->cache(600)->leftJoin('mall_product g','g.id = o.goods_id')->leftJoin('mall_dettype d','d.pid = g.id')->where([['o.add_time','between',$data],['o.order_status','in',[1,2,3]],['d.prid','eq',11]])->sum('o.order_amount');
        $nongte = Db::name('mall_order o')->cache(600)->leftJoin('mall_product g','g.id =o.goods_id')->leftJoin('mall_dettype d','d.pid = g.id')->where([['o.add_time','between',$data],['o.order_status','in',[1,2,3]],['d.prid','eq',16]])->sum('o.order_amount');
        //健康大使
        $totalNum = Db::name('mall_weight_list')->where([['create_time','between',$data]])->count('id');
        $weight_list = Db::name('mall_weight m')->where([['w.create_time','between',$data]])->field('m.name,count(w.id) as num')->leftJoin('mall_weight_list w','m.id = w.weight_id')->group('m.id')->select();
        $array = [
            ['name'=>'时间区间','num'=>"$data[0] - $data[1]"],
            ['name'=>'新增充值金额','num'=>$rechargeBal],
            ['name'=>'新增消费积分','num'=>$integral],
            ['name'=>'新增贡献积分','num'=>$points],
            ['name'=>'新增福分','num'=>$lot],
            ['name'=>'新增冻结福分','num'=>$freeze_lot],
            ['name'=>'新增绿色积分','num'=>$green_points],
            ['name'=>'新增提现','num'=>$withdraw],
            ['name'=>'新增会员','num'=>$newMember],
            ['name'=>'体验会员','num'=>$Member],
            ['name'=>'正式会员','num'=>$vipMember],
            ['name'=>'新增城市代理','num'=>$city_agency],
            ['name'=>'新增驿站代理','num'=>$station_agent],
            ['name'=>'新增行政区代理（区县级）','num'=>$v1],
            ['name'=>'新增行政区代理（市级）','num'=>$v2],
            ['name'=>'新增健康大使','num'=>$totalNum],
            ['name'=>'预约单量','num'=>$Order],
            ['name'=>'预约额','num'=>$OrderBal],
            ['name'=>'中单量','num'=>$machineOrder],
            ['name'=>'中单额','num'=>$machineBal],
            ['name'=>'中单发放福分','num'=>$order_lot],
            ['name'=>'未中单量','num'=>$orderNo],
            ['name'=>'未中单发放福分','num'=>$sendLot],
            ['name'=>'商城成单','num'=>$mallOrder],
            ['name'=>'商城成交额','num'=>$mallBal],
            ['name'=>'商城兑换额','num'=>$exchange],
            ['name'=>'养生专区','num'=>$yangsheng],
            ['name'=>'家用专区','num'=>$jiayong],
            ['name'=>'农特专区','num'=>$nongte]
        ];
        $key = [
            'name' => '名称',
            'num|"###\t"' => '数量',
        ];
        $str = Csv::main()->out($array,$key);
        header('Content-Type: application/octet-stream');//告诉浏览器输出内容类型，必须
        header('Content-Disposition: attachment; filename="'.date('Y-m-d H:i:s').'.csv"');
        echo mb_convert_encoding($str,'gbk','utf-8');
    }
    public function account_record(){
        $member_id = input('get.id','','intval');
        $where = 'member_id='.$member_id;
        $type = input('get.type','','intval');
        if($type){
            $where .= ($type==1?' and num>0':' and num<=0');
        }
        $this->_query('account_record')->where($where)->order('id desc')->page();
        return $this->fetch();
    }
    public function commition_withdraw(){
        $where = [];
        if(input('name')) {
            $where[] = ['a.name|a.mobile|m.id|m.tel','like','%'.input('name').'%'];
        }
        if(input('bank_no')) {
            $where[] = ['a.bank_no','like','%'.input('bank_no').'%'];
        }
        if(input('type')!='') {
            $where[] = ['a.type','eq',input('type')];
        }
        /*
        if(input('status')!='') {
            $where[] = ['a.status','eq',input('status')];
        }
        */
        if(input('status')!='') {
            $where[] = ['a.status','in',input('status')];
        }
        if(input('status')=='') {
            $where[] = ['a.status','in','0,1,2,4'];
        }
        
        if(input('group_tel')) {
            $group_tel = input('group_tel');
            $member_id = Db::name('member')->where(['tel'=>$group_tel])->value('id');
            if(!$member_id){
                $this->error('查无此人');
            }
            $isd = Db::name('member')->where("FIND_IN_SET({$member_id},id_path)")->column('id');
            $where[] = ['a.member_id','in',$isd];
        }
        $time = input('get.add_time');
        if(isset($time) && $time){
            $aa = explode(' - ',$time);
            $where[] = ['a.create_time','between',[$aa[0],$aa[1]]];
        }else{
           
            $today = date('Y-m-d');  
            $thirtyDaysAgo = date('Y-m-d', strtotime('-90 days'));  
            /*$where[] = ['a.create_time','gt',$thirtyDaysAgo];*/
        
        }
        
        
        $num = Db::name("commission_withdraw a")->join('member m','m.id=a.member_id')->where($where)->sum('a.money');
        $this->assign('num',$num);
        /*
$result = implode(',', Db::name('commission_withdraw')->group('mobile')->having('COUNT(mobile) = 1')->column('mobile'));
$this->assign('double',$result);
*/
$resultx = Db::name('sys_notice')->column('imgurl');
$concatenatedImgurls = implode(',', $resultx);
$this->assign('double', $concatenatedImgurls);

    $records = Db::name('log')->where(['type' => '99'])->select();  
    $result = '';  
    foreach ($records as $record) {  
    $item = $record['level'] . ',' . $record['msg'];  
    if (!empty($result)) {$item = '|' . $item;}  
    $result .= $item;}  
    $this->assign('incash', $result);


    //$this->_query("commission_withdraw a")->field('a.*,m.tel')->join('member m','m.id=a.member_id')->where($where)->order('a.id desc')->page();
    
    //2024-12-01 数据删除依然需要显示提现记录
    //$this->_query("commission_withdraw a")->field('a.*,m.tel')->join('member m','m.id=a.member_id')->join('member_yunzhong q','m.id=q.member_id')->field('q.card_id as order_sn')->where($where)->order('a.id desc')->page();  
    
    $this->_query("commission_withdraw a")
    ->field('a.*, m.tel, COALESCE(q.card_id, \'已删除\') as order_sn')
    ->join('member m', 'm.id = a.member_id', 'LEFT')  // 使用 LEFT JOIN 确保即使 member 没有匹配的 commission_withdraw 记录也会返回
    ->join('member_yunzhong q', 'm.id = q.member_id', 'LEFT')  // 使用 LEFT JOIN 确保即使 member_yunzhong 没有匹配的记录也会返回
    ->where($where)
    ->order('a.id desc')
    ->page();
    
    }
    
    
    
    public function main_team()
    {
        $member_idx = input('user_id');
        $telx = input('tel');
        $member_listx = Db::name('member')->field('tel,id,depth,parent_id,city_agency,station_agent')->where("FIND_IN_SET({$member_idx},id_path)")->select();
        $datax = $this->getTree_team($member_listx, $member_idx);
        $this->assign([
            'data'=>[
                'name'=>$telx,
                'children'=>$datax
            ]
        ]);
        $this->fetch();
    }
    
    public function getTree_team($list,$pid)
    {
        //定义空数组
        $treex = [];
        //循环原来的数据，进行父亲找儿子的操作
        foreach($list as $k => $v)
        {
            
            if ($v['city_agency'] > 0){
            $valx = 1;
            $symbol = 'image://https://shop.gxqhydf520.com/upload/e3512c6bc538ea98/shidai.png';
            }elseif($v['station_agent'] > 0)
            {$valx = 2;
            $symbol = 'image://https://shop.gxqhydf520.com/upload/e3512c6bc538ea98/yizhan.png';
            }
            else{$valx = 0;
            $symbol = 'image://https://shop.gxqhydf520.com/upload/e3512c6bc538ea98/start.png';
            }
            
            if($v['parent_id'] == $pid)
            {
                $treex[] = [
                    'name' =>$v['tel'],
                    'value' => $valx,
                    'symbol' => $symbol,
                    'children' => $this->getTree_team($list, $v['id'])
                ];
            }
        }
        return $treex;
    }
    
    
    
    public function main()
    {
        $member_id = input('user_id');
        $tel = input('tel');
        $member_list = Db::name('member')->field('tel,id,depth,parent_id')->where("FIND_IN_SET({$member_id},id_path)")->select();
        $data = $this->getTree($member_list, $member_id);
        $this->assign([
            'data'=>[
                'name'=>$tel,
                'children'=>$data
            ]
        ]);
        $this->fetch();
    }
    
    public function teams()
{
    $member_id = input('user_id');
    $tel = input('tel');
    
    // 获取配置值
    $config_value = Db::name('config')->where(['id' => '43'])->value('val');
    
    // 将配置值拆分为数组
    $config_ids = explode(',', $config_value);
    
    // 如果 $member_id 在配置值中，去掉它
    if (($key = array_search($member_id, $config_ids)) !== false) {
        unset($config_ids[$key]);
    }
    
    // 获取 $member_id 对应的 depth
    $member_depth = Db::name('member')
        ->where('id', $member_id)
        ->value('depth');
    
    // 筛选 $config_ids，只保留 depth 大于等于 $member_depth 的值
    $filtered_config_ids = [];
    foreach ($config_ids as $id) {
        $config_depth = Db::name('member')
            ->where('id', $id)
            ->value('depth');
        
        if ($config_depth >= $member_depth) {
            $filtered_config_ids[] = $id;
        }
    }
    
    // 构建排除条件
    $exclude_conditions = [];
    foreach ($filtered_config_ids as $id) {
        $exclude_conditions[] = "id_path NOT LIKE '%,{$id},%'";
    }
    
    // 如果 $filtered_config_ids 不为空，则添加排除条件
    $exclude_condition = '';
    if (!empty($exclude_conditions)) {
        $exclude_condition = implode(' AND ', $exclude_conditions);
    }
    
    // 查询成员列表
    $query = Db::name('member')
        ->field('tel,id,depth,parent_id,city_agency,station_agent')
        ->whereRaw("FIND_IN_SET(?, id_path)", [$member_id]);
    
    // 如果 $exclude_condition 不为空，则添加排除条件
    if (!empty($exclude_condition)) {
        $query->whereRaw($exclude_condition);
    }
    
    $member_list = $query->select();
    
    $data = $this->getTree_team_t($member_list, $member_id);
    
    $this->assign([
        'data' => [
            'name' => $tel,
            'children' => $data
        ]
    ]);
    
    $this->fetch();
}
    public function mains()
    {
    $member_id = input('user_id');
    $records = Db::name('merits_record')->where(['member_id' => $member_id])->order('create_time desc')->limit(200)->select();  
    $result = '';  
    foreach ($records as $record) {  
    $item = '用户ID：'.$record['member_id'] . ' 错误类型：' . $record['type'] . ' 数值：' . $record['num']. ' 描述：' . $record['desc']. ' 时间：' . $record['create_time'];  
    if (!empty($result)) {  
        $item = ' | ' . $item;  
    }  
    $result .= $item;  
    }  
    $this->assign('data', $result);
        $this->fetch();
    }
    
    public function getTree($list,$pid)
    {
        //定义空数组
        $tree = [];
        //循环原来的数据，进行父亲找儿子的操作
        foreach($list as $k => $v)
        {
            if($v['parent_id'] == $pid)
            {
                $tree[] = [
                    'name' =>$v['tel'],
                    'value' => $v['depth'],
                    'children' => $this->getTree($list, $v['id'])
                ];
            }
        }
        return $tree;
    }
    
    public function getTree_team_t($list,$pid)
    {
        //定义空数组
        $treex = [];
        //循环原来的数据，进行父亲找儿子的操作
        foreach($list as $k => $v)
        {
            
            if ($v['city_agency'] > 0){
            $valx = 1;
            $symbol = 'image://https://shop.gxqhydf520.com/upload/e3512c6bc538ea98/shidai.png';
            }elseif($v['station_agent'] > 0)
            {$valx = 2;
            $symbol = 'image://https://shop.gxqhydf520.com/upload/e3512c6bc538ea98/yizhan.png';
            }
            else{$valx = 0;
            $symbol = 'image://https://shop.gxqhydf520.com/upload/e3512c6bc538ea98/start.png';
            }
            
            if($v['parent_id'] == $pid)
            {
                $treex[] = [
                    'name' =>$v['tel'],
                    'value' => $valx,
                    'symbol' => $symbol,
                    'children' => $this->getTree_team($list, $v['id'])
                ];
            }
        }
        return $treex;
    }
    
    
    
    
    public function  commition_withdraw_complete(){
        if(request()->isPost()){
            $id = input('id');
            $client_ip = 'withdraw_complete'.$id;
            if(cache($client_ip)){
                return [
                    'status' => false,
                    'msg' => '请勿重复点击',
                ];
            }
            cache('withdraw_complete',$client_ip,50);
            $info = Db::name('commission_withdraw')->where(['id'=>$id])->find();
            if($info['status']!=0){
                return [
                    'status' => false,
                    'msg' => '流程错误',
                ];
            }
            Db::name('commission_withdraw')->where(['id'=>$id])->update([
                'status'=>3
            ]);
            return [
                'status' => true,
                'msg' => '操作成功',
            ];
        }
    }
    public function  commition_withdraw_complete_x(){
        if(request()->isPost()){
            
            $tel = input('id');
            // 检是否已经存在该电话号码
            $exists = Db::name('sys_notice')->where('imgurl', $tel)->find();
             
            if (!$exists) {
                // 如果不存在，则插入新记录
                Db::name('sys_notice')
                    ->insert(['imgurl' => $tel]);
            } else {
                Db::name('sys_notice')
                ->where('imgurl', $tel)
                ->delete();
            }
            
            return [
                'status' => true,
                'msg' => '操作成功',
            ];
        }
    }


    /**
     * 纠正银行卡状态
     * @return array
     */
    public function unlock_state(){
        
        /*hack*/
        /*
        return [
                'status' => false,
                'msg' => '失败 请稍后再试',
        ];
        */
        $id = input('id');
        $member = Db::name('member')->where(['id'=>$id])->find();
        if(!$member){
            return [
                'status' => false,
                'msg' => '查无此人',
            ];
        }
        $payInfo =  Db::name('member_yunzhong')->where(['member_id'=>$id])->find();
        if(!$payInfo){
            return [
                'status' => false,
                'msg' => '没有相关信息',
            ];
        }
        Db::startTrans();
        try {
            Db::name('member_yunzhong')->where(['member_id'=>$id])->update(['status'=>1]);
            Db::commit();
            return [
                'status' => true,
                'msg' => '操作成功',
            ];
        }catch (Exception $exception){
            Db::rollback();
            return [
                'status' => true,
                'msg' => '失败 请稍后再试',
            ];
        }
    }

    private function ipContent($ip)
    {
        $url = 'https://opendata.baidu.com/api.php?query='.$ip.'&co=&resource_id=6006&oe=utf8';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $output = curl_exec($ch);
        curl_close($ch);
        
        // 对JSON字符串进行解码
        $result = json_decode($output, true);
        
        // 检查解码是否成功，并且data数组和address字段是否存在
        if (is_array($result) && isset($result['data']) && isset($result['data'][0]['location'])) {
            return $result['data'][0]['location']; // 返回address字段的字符串
        } else {
            return 'Error retrieving address'; // 或者返回其他错误信息
        }
    }
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
     * 解绑银行卡
     * @return void
     */
    public function unlock_pay(){
        /*hack*/
        /*
        return [
                'status' => false,
                'msg' => '失败 请稍后再试',
        ];
        */
        $id = input('id');
        $ip = $this->get_real_ip();
		$result = $this->ipContent($ip);
        Db::name('log')->insert([
        'level' => '解绑银行卡',
        'type' => '999',
        'msg' => 'ID：'.$id.' | '.$ip.' | '.$result.'|'.session('user.username'),
        ]);
        
        $member = Db::name('member')->where(['id'=>$id])->find();
        if(!$member){
            return [
                'status' => false,
                'msg' => '查无此人',
            ];
        }
        $payInfo =  Db::name('member_yunzhong')->where(['member_id'=>$id])->find();
        if(!$payInfo){
            return [
                'status' => false,
                'msg' => '没有相关信息',
            ];
        }
        
        $where = [['status','<>',3],['status','<>',5],['member_id','=',$id]];
        $commission_withdraw = Db::name('commission_withdraw')->where($where)->find();
        if($commission_withdraw){
            return [
                'status' => false,
                'msg' => '该用户仍有未完成的提现记录,请操作后再试！',
            ];
        }
        
        Db::startTrans();
        try {
            $yunzhong = new YunZhong();
            $result = $yunzhong->del_member($payInfo['facilitator_id']);
            if($result['code'] == 200){
                Db::name('member_yunzhong')->where(['member_id'=>$id])->delete();
                Db::name('member_pay')->where(['member_id'=>$id])->delete();
            }else{
                return [
                    'status' => false,
                    'msg' => $result['msg'],
                ];
            }
            Db::commit();
            return [
                'status' => true,
                'msg' => '操作成功',
            ];
        }catch (Exception $exception){
            Db::rollback();
            return [
                'status' => true,
                'msg' => '失败 请稍后再试',
            ];
        }

    }
    public function commition_withdraw_adopt(){
        if(request()->isPost()){
            $id = input('id');
            $client_ip = 'withdraw_adopt'.$id;
            if(cache($client_ip)){
                return [
                    'status' => false,
                    'msg' => '请勿重复点击',
                ];
            }
            cache('withdraw_adopt',$client_ip,50);
            $info = Db::name('commission_withdraw')->where(['id'=>$id])->find();
            if($info['status']!=0){
                return [
                    'status' => false,
                    'msg' => '流程错误',
                ];
            }
            $memberAush = Db::name('member_yunzhong')->where(['member_id'=>$info['member_id'],'status'=>1])->find();
            if(!$memberAush){
                return [
                    'status' => false,
                    'msg' => '用户尚未通过电子签约!',
                ];
            }
            $yunZhong = new YunZhong();
            $parameters = [
                "trade_number"=>$info['order_sn'],
                "crowd_id"=>14288,
                "issuing_data" =>[[
                    "professional_id"=>$memberAush['professional_id'],
                    "name"=>$memberAush['name'],
                    "cer_code"=>$memberAush['card_id'],
                    "mobile"=>$memberAush['mobile'],
                    "bank_code"=>$info['bank_no'],
                    "money"=>$info['money'],
                    "remark"=>"服务费",
                    "request_no"=>$info['order_sn'],
                    "professional_bank_id"=>0,
                    "resolve_id"=>5107
                ]]

            ];
            $withdraw = $yunZhong->payment($parameters);
            if($withdraw['code'] == 200){
                Db::name('commission_withdraw')->where(['id'=>$id])->update([
                    'status'=>1
                ]);
                return [
                    'status' => true,
                    'msg' => '操作成功',
                ];
            }else{
                return [
                    'status' => false,
                    'msg' => $withdraw['msg'],
                ];
            }

        }
    }
    
    /*修改手机号*/
    public function fix_phone(){
        
        /*hack*/
        /*
        return [
                'status' => false,
                'msg' => '失败 请稍后再试',
        ];
        */
        if(request()->isPost()){
            $id = input('id');
            $new_tel = input('content');
            
            if(!preg_match('/^1[3-9]\d{9}$/',$new_tel)){
                return [
                    'status' => false,
                    'msg' => '手机号有误',
                ];
            }
            $info = Db::name('member')->where(['tel'=>$new_tel])->find();
            $info_old_tel = Db::name('member')->where(['id'=>$id])->find();
            if ($info){
                return [
                    'status' => false,
                    'msg' => '该手机号已存在:'.$info_old_tel['tel'],
                ];
            }
            Db::name('member')->where(['id'=>$id])->update([
                    'tel'=>input('content'),
                    'token'=>''
            ]);
        
            $ip = $this->get_real_ip();
    		$result = $this->ipContent($ip);
            
            Db::name('log')->insert([
            'level' => '后台修改手机号',
            'type' => '77',
            'msg' => $ip.' | '.$result.' | 原手机:'.$info_old_tel['tel'].' | 新手机:'.$new_tel.'|'.session('user.username'),
            ]);
        
            return [
                    'status' => true,
                    'msg' => '手机号修改成功',
            ];
        }
    }
    
    
    public function xinren_phone(){
        
        /*hack*/
        /*
        return [
                'status' => false,
                'msg' => '失败 请稍后再试',
        ];
        */
    if(request()->isPost()){
        $id = input('id');
        $new_tel = input('content');
        
        $isValid = false;
        $updateDb = false;

        // 检查 $new_tel 是否有效
        if ($new_tel == '0' || $new_tel == 0) {
            $isValid = true; // 等于0则视为有效
            $updateDb = true; // 并且需要更新数据库
        } elseif (preg_match('/^1[3-9]\d{9}$/', $new_tel)) {
            $isValid = true; // 匹配正则表达式则视为有效
            $updateDb = true; // 并且需要更新数据库
        }

        if ($isValid) {
            if ($updateDb) {
                // 注意：这里应该更新正确的字段，比如 'phone' 而不是 'message_id'
                // 假设正确的字段是 'phone'
                Db::name('member')->where(['id' => $id])->update(['message_id' => $new_tel]);
            }

            $ip = $this->get_real_ip();
            $result = $this->ipContent($ip);
            Db::name('log')->insert([
                'level' => '后台修改信任手机号',
                'type' => '77',
                'msg' => $ip . ' | ' . $result . ' | 原ID:' . $id . ' | 新手机:' . $new_tel.'|'.session('user.username'),
            ]);

            return [
                'status' => true,
                'msg' => '信任手机号修改成功',
            ];
        } else {
            return [
                'status' => false,
                'msg' => '手机号有误',
            ];
        }
        }
    }
    
    /**
     * 审核拒绝
     */
    public function commition_withdraw_reject(){
        if(request()->isPost()){
            $id = input('id');
            $info = Db::name('commission_withdraw')->where(['id'=>$id])->find();
            if($info['status']!=0 && $info['status']!=4){
                return [
                    'status' => false,
                    'msg' => '不支持该操作',
                ];
            }
            Db::startTrans();
            try {
                Db::name('commission_withdraw')->where(['id'=>$id])->update([
                    'status'=>5,
                    'update_time'=>date('Y-m-d H:i:s'),
                    'notice'=>input('content')
                ]);
                $total = $info['money']+$info['free'];

                Db::name('member')->where(['id'=>$info['member_id']])->setInc('integral',$total);

                Db::name('bal_record')->insert([
                    'number'=>$total,
                    'type'=>30,
                    'info'=>'提现驳回',
                    'member_id'=>$info['member_id']
                ]);
                Db::commit();
                return [
                    'status' => true,
                    'msg' => '操作成功',
                ];
            }catch (Exception $exception){
                Db::rollback();
                return [
                    'status' => true,
                    'msg' => '失败 请稍后再试',
                ];
            }


        }
    }
    /*
    public function bal_record(){
        $where = [];
        if(input('tel')) {
            $where[] = ['m.tel','like','%'.input('tel').'%'];
        }
        $type = input('vip_level');
        if(input('vip_level')) {
            if ($type == -1){
                $type = 0;
            }
            $where[] = ['a.type','eq',$type];
        }
        $time = input('get.add_time');
        if(isset($time) && $time){
            $aa = explode(' - ',$time);
            $where[] = ['a.add_time','between',[$aa[0],$aa[1]]];
        }
        $num = Db::name('bal_record a')->join('member m','m.id=a.member_id')->where($where)->sum('a.number');
        $this->assign('num',$num);
        $this->_query("bal_record a")->field('a.*,m.tel')->join('member m','m.id=a.member_id')->where($where)->order('a.id desc')->page();
    }
    */
    /*
    public function bal_record(){
        $where = [];
        if(input('tel')) {
            $where[] = ['m.tel','like','%'.input('tel').'%'];
        }else{
            $where[] = ['a.member_id','eq','0'];
            //载入时手机号为空
        }
        $type = input('vip_level');
        if(input('vip_level')) {
            if ($type == -1){
                $type = 0;
            }
            $where[] = ['a.type','eq',$type];
        }
        $time = input('get.add_time');
        if(isset($time) && $time){
            $aa = explode(' - ',$time);
            $where[] = ['a.add_time','between',[$aa[0],$aa[1]]];
        }
        
        if(input('tel')) {
        $num = Db::name('bal_record a')->join('member m','m.id=a.member_id')->where($where)->cache(60)->sum('a.number');
        $this->assign('num',$num);
        $this->_query("bal_record a")->field('a.*,m.tel')->join('member m','m.id=a.member_id')->where($where)->order('a.id desc')->cache(60)->page();
        }else{
        $num = Db::name('bal_record a')->where($where)->cache(60)->sum('a.number');
        $this->assign('num',$num);
        $this->_query("bal_record a")->where($where)->cache(60)->order('a.id desc')->page();
        }
    }
    */
    public function bal_record(){
        $where = [];
        if(input('tel')) {
            $where[] = ['m.tel','like','%'.input('tel').'%'];
        }else{
            $where[] = ['a.member_id','eq','0'];
            //载入时手机号为空
        }
        $type = input('vip_level');
        if(input('vip_level')) {
            if ($type == -1){
                $type = 0;
            }
            $where[] = ['a.type','eq',$type];
        }
        if (in_array($type, config('integral_type'))) {
            $db_name_a = 'bal_record_integral_history a';
        } elseif (in_array($type, config('points_type'))) {
            $db_name_a = 'bal_record_points_history a';
        } elseif (in_array($type, config('green_points'))) {
            $db_name_a = 'bal_record_green_history a';
        } elseif (in_array($type, config('lot_type'))) {
            $db_name_a = 'bal_record_lot_history a';
        } elseif (in_array($type, config('freeze_lot'))) {
            $db_name_a = 'bal_record_freeze_history a';
        } else {
            //echo "请选择选择类型";
            $db_name_a = 'bal_record_points_history a';
        }
        
        $time = input('get.add_time');
        if(isset($time) && $time){
            $aa = explode(' - ',$time);
            $where[] = ['a.add_time','between',[$aa[0],$aa[1]]];
        }
        $db_type = input('db_type',1);
        $this->assign('db_type',$db_type);
        if($db_type == 1){
            $db_name = 'bal_record a';
        }else{
            $db_name = $db_name_a;
        }
        
        if(input('tel')) {
            $num = Db::name('bal_record a')->join('member m','m.id=a.member_id')->where($where)->cache(60)->sum('a.number');
            $num2 = Db::name($db_name_a)->join('member m','m.id=a.member_id')->where($where)->cache(60)->sum('a.number');
            $this->assign('num',$num+$num2);
            $this->_query($db_name)->field('a.*,m.tel')->join('member m','m.id=a.member_id')->where($where)->order('a.id desc')->cache(60)->page();
        }else{
            $num = Db::name('bal_record a')->where($where)->cache(60)->sum('a.number');
            $num2 = Db::name($db_name_a)->where($where)->cache(60)->sum('a.number');
            $this->assign('num',$num+$num2);
            $this->_query($db_name)->where($where)->order('a.id desc')->cache(60)->page();
        }
    }
    
    public function limit_status(){
        $id = input('id');
        $type = input('type');
        if($type == 'true'){
            Db::name('vip_log')->insert([
                'member_id'=>$id,
                'type'=>1,
                'vip_level'=>1
            ]);
            Db::name('member')->where(['id'=>$id])->update(['city_agency'=>1]);
        }else{
            Db::name('vip_log')->where(['member_id'=>$id,'type'=>1])->delete();
            Db::name('member')->where(['id'=>$id])->update(['city_agency'=>0]);
        }
        $this->success('操作成功');
    }
    public function station_agent(){
        $id = input('id');
        $type = input('type');
        if($type == 'true'){
            Db::name('vip_log')->insert([
                'member_id'=>$id,
                'type'=>2,
                'vip_level'=>2
            ]);
            Db::name('member')->where(['id'=>$id])->update(['station_agent'=>1]);
        }else{
            Db::name('vip_log')->where(['member_id'=>$id,'type'=>2])->delete();
            Db::name('member')->where(['id'=>$id])->update(['station_agent'=>0]);
        }
        $this->success('操作成功');
    }
    /**
     * @return void 留言管理
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function message(){
        $this->title = "投诉建议";
        $where = [];
        if(input('account')) {
            $where[] = ['m.tel','like','%'.input('account').'%'];
        }
        if(input('status') != '') {
            $where[] = ['w.status','eq',input('status')];
        }
        $this->_query('message_user w')->field('w.id,w.add_time,w.type,m.tel,w.content,w.imge')->join('member m','w.member_id=m.id')->where($where)->order('w.id desc')->page();
    }

    public function message_del(){
        Db::name('message_user')->where([['id','in',input('post.id')]])->delete();
        $this->success('删除成功');
    }
}