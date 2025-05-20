<?php

namespace app\manage\controller;

use library\Controller;
use think\Db;
use think\Exception;

class Coupon extends Controller
{
    /**
     * @return void 优惠券列表
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function index(){
        $where=[];
        if(input('title')) {
            $where[] = ['title','like','%'.input('title').'%'];
        }
        $this->_query("coupon_info")->where($where)->order('id desc')->page();
    }
    public function pick(){
        $where=[];
        if(input('title')) {
            $where[] = ['title','like','%'.input('title').'%'];
        }
        $this->_query("coupon_pick_conf")->where($where)->order('id desc')->page();
    }
    /**
     * @return void 优惠券编辑
     */
    public function coupon_edit(){
            $this->title = '添加优惠券';
            $this->_form('coupon_info', 'coupon_edit', 'id');
    }

    /**
     * @return void 权益券编辑
     */
    public function pick_edit(){
        $this->title = '添加优惠券';
        $type = Db::name('mall_product')->where([['status','=',1], ['stock','>',0],['is_del','=',0],['goods_type','eq',3]])->column('title','id');
        $this->assign('type',$type);
        if(request()->isPost()){
            $data = input('post.');
            if($data['time_type'] == 1){//按时间
                if(!$data['start_time']){
                    $this->error('请选择开始时间');
                }
                if(!$data['end_time']){
                    $this->error('请选择结束时间');
                }
                if($data['start_time']>$data['end_time']){
                    $this->error('结束时间不得大于开始时间');
                }
            }else{
                if(!$data['day']){
                    $this->error('请输入过期天数');
                }
            }
        }
        $this->_form('coupon_pick_conf', 'pick_edit', 'id');
    }
    public function pick_del(){
        Db::name('coupon_pick')->where([['id','in',input('post.id')]])->delete();
        $this->success('删除成功');
    }
    public function del(){
        Db::name('coupon_list')->where([['id','in',input('post.id')]])->delete();
        $this->success('删除成功');
    }
    /**
     * @return void 用户优惠券列表
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function coupon_list(){
        $this->title='用户优惠券列表';
        $where=[];
        if(input('title')) {
            $where[] = ['c.title','like','%'.input('title').'%'];
        }
        if(input('coupon_id')) {
            $where[] = ['c.coupon_id','eq',input('coupon_id')];
        }
        if(input('status')) {
            $where[] = ['c.status','eq',input('status')];
        }
        $coupon = Db::name('coupon_info')->column('title','id');
        $this->assign([
            'coupon'=>$coupon
        ]);
        $this->_query("coupon_list c")->field('c.*,m.tel,m.id as mid')->leftJoin('member m','m.id=c.member_id')->where($where)->order('id desc')->page();
    }

    public function config_del(){
        if(input('post.id') == 1){
            $this->error('新人礼包不得删除');
        }
        Db::name('coupon_pick_conf')->where([['id','in',input('post.id')]])->delete();
        $this->success('删除成功');
    }
    public function coupon_pick(){
        $this->title = '用户权益券列表';
        $where=[];
        if(input('title')) {
            $where[] = ['c.title','like','%'.input('title').'%'];
        }
        if(input('status')) {
            $where[] = ['c.status','eq',input('status')];
        }
        $this->_query("coupon_pick c")->field('c.*,m.tel,m.id as mid')->leftJoin('member m','m.id=c.member_id')->where($where)->order('id desc')->page();
    }

    /**
     * @return void 发放优惠券
     */
    public function provide(){
        if(request()->isGet()){
            $this->title = '优惠券发放';
            $coupon = Db::name('coupon_info')->where(['status'=>1])->column('title','id');
            $this->assign([
                'coupon'=>$coupon
            ]);
            $this->fetch();
        }else{
            $data = input('post.');
            $coupon_info = Db::name('coupon_info')->where([['id','eq',$data['coupon_id']],['status','eq',1]])->find();
            if(!$data['tel']){
                $this->error('请输入手机号');
            }
            if(!$data['coupon_id']){
                $this->error('请选择优惠券');
            }
            if(!$coupon_info){
                $this->error('该优惠券已过期或已关闭');
            }
            if(!$data['num']){
                $this->error('请输入发放数量');
            }
            $n_time = date('Y-m-d H:i:s');
            if(!($n_time < $coupon_info['end_time'])){
                $this->error('该优惠券不在活动时间内');
            }
            $idArr = Db::name('member')->where([['tel','in',$data['tel']]])->column('id');
            if(!$idArr){
                $this->error('无效用户');
            }
            Db::startTrans();
            try{
                $dataArr = $insertData =  [];
                foreach ($idArr as $id){
                    $dataArr[] = [
                        'title'=>$coupon_info['title'],
                        'coupon_id'=>$coupon_info['id'],
                        'member_id'=>$id,
                        'full_money'=>$coupon_info['full_money'],
                        'money'=>$coupon_info['money'],
                        'start_time'=>$coupon_info['start_time'],
                        'end_time'=>$coupon_info['end_time']
                    ];
                }
                $num = 1;
                while($num <= $data['num']) {
                    foreach ($dataArr as $value){
                        $insertData[] = $value;
                    }
                    $num++;
                }
                Db::name('coupon_list')->insertAll($insertData);
                Db::commit();
                $this->success('发放成功');
            }catch (Exception $exception){
                Db::rollback();
                $this->error('发放失败，请稍后再试');
            }
        }
    }

    /**
     * @return void 发放优惠券
     */
    public function provide_pick(){
        if(request()->isGet()){
            $this->title = '权益券发放';
            $coupon = Db::name('coupon_pick_conf')->where(['status'=>1])->column('title','id');
            $this->assign([
                'coupon'=>$coupon
            ]);
            $this->fetch();
        }else{
            $data = input('post.');
            if(!$data['tel']){
                $this->error('请输入手机号');
            }
            if(!$data['coupon_id']){
                $this->error('请选择优惠券');
            }
            $coupon_info = Db::name('coupon_pick_conf')->where([['id','eq',$data['coupon_id']],['status','eq',1]])->find();
            if(!$coupon_info){
                $this->error('该权益券已过期或已关闭');
            }
            if(!$data['num']){
                $this->error('请输入发放数量');
            }
            if($coupon_info['time_type'] == 1){
                $n_time = date('Y-m-d H:i:s');
                if(!($n_time < $coupon_info['end_time'])){
                    $this->error('该优惠券不在活动时间内');
                }
                $start_time = $coupon_info['start_time'];
                $end_time = $coupon_info['end_time'];

            }else{
                $start_time = date('Y-m-d');
                $end_time = date('Y-m-d',strtotime("+".$coupon_info['day']." day"));
            }

            $idArr = Db::name('member')->where([['tel','in',$data['tel']]])->column('id');
            if(!$idArr){
                $this->error('无效用户');
            }
            Db::startTrans();
            try{
                $dataArr = $insertData =  [];
                foreach ($idArr as $id){
                    $dataArr[] = [
                        'title'=>$coupon_info['title'],
                        'coupon_id'=>$coupon_info['id'],
                        'imgurl'=>$coupon_info['imgurl'],
                        'member_id'=>$id,
                        'goods_id'=>$coupon_info['goods_id'],
                        'money'=>$coupon_info['money'],
                        'start_time'=>$start_time,
                        'end_time'=>$end_time
                    ];
                }
                $num = 1;
                while($num <= $data['num']) {
                    foreach ($dataArr as $value){
                        $insertData[] = $value;
                    }
                    $num++;
                }
                Db::name('coupon_pick')->insertAll($insertData);
                Db::commit();
                $this->success('发放成功');
            }catch (Exception $exception){
                Db::rollback();
                $this->error('发放失败，请稍后再试');
            }
        }
    }
}