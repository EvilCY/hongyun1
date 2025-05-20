<?php
/**
 * 矿机管理
 */

namespace app\manage\controller;
use app\index\controller\Push;
use app\lib\Csv;
use library\Controller;
use think\Db;
use think\Exception;


class Machine extends Controller
{
    public function index(){
        $where=[];
        if(input('title')) {
            $where[] = ['mname','like','%'.input('title').'%'];
        }
        if(input('status') != '') {
            $where[] = ['status','eq',input('status')];
        }
        $this->_query("machine")->where($where)->order('id desc')->page();
    }
    public function machine_pick(){
        $this->title='用户提货券列表';
        $where=[];
        if(input('title')) {
            $where[] = ['e.mname|c.order_sn','like','%'.input('title').'%'];
        }
        if(input('status')) {
            $where[] = ['c.status','eq',input('status')];
        }
        $this->_query("machine_pick c")->field('c.*,m.tel,m.id as mid,e.mname')->leftJoin('machine e','e.id = c.machine_id')->leftJoin('member m','m.id=c.member_id')->where($where)->order('id desc')->page();
    }
    public function machine_close(){
        $this->title='用户平仓券列表';
        $where=[];
        if(input('title')) {
            $where[] = ['e.mname|c.order_sn','like','%'.input('title').'%'];
        }
        if(input('status')) {
            $where[] = ['c.status','eq',input('status')];
        }
        $this->_query("machine_close c")->field('c.*,m.tel,m.id as mid,e.mname')->leftJoin('machine e','e.id = c.machine_id')->leftJoin('member m','m.id=c.member_id')->where($where)->order('id desc')->page();
    }
    public function machine_init(){
        $where=[];
        if(input('title')) {
            $where[] = ['mname','like','%'.input('title').'%'];
        }
        if(input('status')) {
            $where[] = ['status','eq',input('status')];
        }
        $this->_query("machine_init")->where($where)->order('id asc')->page();
    }
    public function machine_init_edit(){
        if(!input('id')) {
            $this->error('操作失败！');
        }
        $this->applyCsrfToken();
        $this->_form('machine_init', 'machine_init_edit', 'id');
    }
    public function machine_edit(){
        //$this->applyCsrfToken();
        //$this->_form('machine', 'machine_edit', 'id');
    }
    public function machine_status(){
        $id = input('id');
        $status = input('status');
        Db::name('machine')->where(['id'=>$id])->update([
            'status'=>$status
        ]);
        return[
            'code' => 1,
            'info' => '操作成功',
        ];
    }

    /**
     * 顺顺福订单列表
     * @return void
     */
    public function machine_order(){
        $where=[];
        if(input('title')) {
            $where[] = ['m.mname|u.tel','like','%'.input('title').'%'];
        }
        if(input('status')) {
            $where[] = ['o.status','in',input('status')];
        }
        if(input('id')) {
            $where[] = ['o.machine_id','eq',input('id')];
        }
        if(input('group_tel')) {
            $group_tel = input('group_tel');
            $member_id = Db::name('member')->where(['tel'=>$group_tel])->value('id');
            if(!$member_id){
                $this->error('查无此人');
            }
            $isd = Db::name('member')->where("FIND_IN_SET({$member_id},id_path)")->column('id');
            $where[] = ['o.member_id','in',$isd];
        }
        //默认不显示数据
        if (empty($where)) {
            $where[] = ['o.member_id','in','0'];
        }
        $time = input('get.end_time');
        if(isset($time) && $time){
            $aa = explode(' - ',$time);
            $where[] = ['o.pay_time','between',[$aa[0],$aa[1]]];
        }else{
            $today = date('Y-m-d');  
            $thirtyDaysAgo = date('Y-m-d', strtotime('-100 days'));  
            $where[] = ['o.pay_time','gt',$thirtyDaysAgo];
        }
        $num = Db::name("machine_order o")->leftJoin("machine m","m.id = o.machine_id")->leftJoin('member u','u.id = o.member_id')->cache(300)->where($where)->sum('o.price');
        $this->assign([
            'num'=>$num
        ]);
        $this->_query("machine_order o")->field('o.*,m.mname,u.nickname,u.tel')->leftJoin("machine m","m.id = o.machine_id")->leftJoin('member u','u.id = o.member_id')->cache(300)->where($where)->order('id desc')->page();
    }
    /**
     * 导出订单数据
     * */
    public function out_order_excel()
    {
        # 搜索条件
        $where=[];
        if(input('title')) {
            $where[] = ['m.mname|u.tel','like','%'.input('title').'%'];
        }
        if(input('status')) {
            $where[] = ['o.status','eq',input('status')];
        }
        if(input('id')) {
            $where[] = ['o.machine_id','eq',input('id')];
        }
        $time = input('get.end_time');
        if(isset($time) && $time){
            $aa = explode(' - ',$time);
            $where[] = ['o.pay_time','between',[$aa[0],$aa[1]]];
        }
        $orderlist = Db::name("machine_order o")->field('o.*,m.mname,u.nickname,u.tel')->leftJoin("machine m","m.id = o.machine_id")->leftJoin('member u','u.id = o.member_id')->where($where)->order('id desc')->select();
        $GLOBALS['status'] = [1=>'已支付',2=>'未中单已退款',3=>'已中单'];
        $key = array(
            'order_sn' => '订单号',
            'price|"###\t"' => '订单金额',
            'tel|"###\t"' => '账号',
            'member_id|"###\t"' => '用户ID',
            'status|$GLOBALS["status"][###]' => '订单状态',
            'pay_time|"###\t"' => '支付时间',
            'cancel_time|"###\t"' => '退款时间',
            'create_time|"###\t"' => '添加时间'
        );
        $str = Csv::main()->out($orderlist,$key);
        header('Content-Type: application/octet-stream');//告诉浏览器输出内容类型，必须
        header('Content-Disposition: attachment; filename="顺顺福预约订单'.date('Y-m-d H:i:s').'.csv"');
        echo mb_convert_encoding($str,'gbk','utf-8');
    }


}