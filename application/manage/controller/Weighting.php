<?php

namespace app\manage\controller;

use library\Controller;
use think\Db;
use think\Exception;

class Weighting extends Controller
{
    public function index(){
        $this->title = '加权列表';
        $where=[];
        if(input('title')) {
            $where[] = ['name','like','%'.input('title').'%'];
        }
        if(input('status')) {
            $where[] = ['status','eq',input('status')];
        }
        $this->_query("mall_weight")->where($where)->order('id desc')->page();
    }

    public function edit(){
        $this->applyCsrfToken();
        $this->_form('mall_weight', 'edit', 'id');
    }
    public function del(){
        Db::name('mall_weight')->where([['id','in',input('post.id')]])->delete();
        Db::name('mall_weight_list')->where([['weight_id','in',input('post.id')]])->delete();
        $this->success('删除成功');
    }
    public function member_del(){
        Db::name('mall_weight_list')->where([['id','in',input('post.id')]])->delete();
        $this->success('删除成功');
    }
    /**
     * @return void 权益券发放
     */
    public function member_edit(){
        if(request()->isGet()){
            $this->title = '权益发放';
            $coupon = Db::name('mall_weight')->where(['status'=>1])->column('name','id');
            $this->assign([
                'coupon'=>$coupon
            ]);
            $this->fetch();
        }else{
            $data = input('post.');
            $coupon_info = Db::name('mall_weight')->where([['id','eq',$data['coupon_id']]])->find();
            if(!$data['tel']){
                $this->error('请输入手机号');
            }
            if(!$data['coupon_id']){
                $this->error('请选择加权');
            }
            if(!$coupon_info){
                $this->error('加权不存在');
            }
            $idArr = Db::name('member')->field('id,tel')->where([['tel','in',$data['tel']]])->group('tel')->select();
            if(!$idArr){
                $this->error('无效用户');
            }
            Db::startTrans();
            try{
                $dataArr =  [];
                foreach ($idArr as $item){
                    $member_info = Db::name('mall_weight_list')->where(['member_id'=>$item['id'],'weight_id'=>$coupon_info['id']])->find();
                    if($member_info){
                        $this->error('用户'.$item['tel'].'已经发放过了,请勿重复发放');
                    }
                    $dataArr[] = [
                        'weight_id'=>$coupon_info['id'],
                        'member_id'=>$item['id'],
                    ];
                }
                Db::name('mall_weight_list')->insertAll($dataArr);
                Db::commit();
                $this->success('发放成功');
            }catch (Exception $exception){
                Db::rollback();
                $this->error('发放失败，请稍后再试');
            }
        }
    }
    /**
     * 顺顺福订单列表
     * @return void
     */
    public function member_list(){
        $this->title = '用户列表';
        $where=[];
        if(input('title')) {
            $where[] = ['m.name|u.tel','like','%'.input('title').'%'];
        }
        if(input('status')) {
            $where[] = ['o.status','eq',input('status')];
        }
        if(input('id')) {
            $where[] = ['o.weight_id','eq',input('id')];
        }
        $mall_weight = Db::name('mall_weight')->column('name','id');
        $this->assign([
            'mall_weight'=>$mall_weight
        ]);
        $this->_query("mall_weight_list o")->field('o.*,m.name,u.nickname,u.tel')->leftJoin("mall_weight m","m.id = o.weight_id")->leftJoin('member u','u.id = o.member_id')->where($where)->order('id desc')->page();
    }
}