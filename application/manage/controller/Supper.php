<?php


namespace app\manage\controller;


use library\Controller;
use think\Db;
use think\Exception;

class Supper extends Controller
{
    /**
     * 店铺编辑
     */
    public function supper(){
        $where = [];
        if(input('title')) {
            $where[] = ['s.title','like','%'.input('title').'%'];
        }
        if(input('tel')!='') {
            $where[] = ['s.tel|m.tel','eq',input('tel')];
        }
        if(input('status') != '') {
            $where[] = ['s.status','eq',input('status')];
        }
        if(input('type') != '') {
            $where[] = ['s.type','eq',input('type')];
        }
        $this->_query("supper s")->field('s.*,m.id as mid,m.tel as mtel')->leftJoin('member m','m.id=s.member_id')->where($where)->order('s.id desc')->page();
    }
    /**
     * 审核通过
     */
    public function auth_adopt(){
        if(request()->isPost()){
            $id = input('id');
            $info = Db::name('supper')->where(['id'=>$id])->find();
            if($info['status']!=0){
                $this->error('不支持该操作');
            }
            Db::startTrans();
            try{
                Db::name('supper')->where(['id'=>$id])->update([
                    'status'=>1
                ]);
                Db::commit();
                $this->success('操作成功');
            }catch (Exception $exception){
                Db::rollback();
                $this->error('失败'.$exception->getMessage());
            }
        }
    }
    public function set_goods(){
        if(request()->isGet()){
            $uid = input('get.id');
            $info = Db::name('supper')->where(['id'=>$uid])->find();
            $goods = Db::name('mall_product')->field('title,id')->where(['goods_type'=>1,'is_del'=>0])->select();
            $goodsList = [];
            if($info['pro_id']){
                $selectGoods = explode(',',$info['pro_id']);
                foreach ($goods as $k=>$value){
                    $goodsList[$k]['name'] = $value['title'];
                    $goodsList[$k]['value'] = $value['id'];
                    foreach ($selectGoods as $item){
                        if($item == $value['id']){
                            $goodsList[$k]['selected'] = true;
                        }
                    }
                }
            }else{
                foreach ($goods as $k=>$value){
                    $goodsList[$k]['name'] = $value['title'];
                    $goodsList[$k]['value'] = $value['id'];
                }
            }
            $this->assign('goodsList', $goodsList);
            $this->assign('info', $info);
            $this->fetch();
        }else{
//            dump(input('post.'));exit;
            Db::name('supper')->where(['id'=>input('post.id')])->setField('pro_id',input('post.pro_id'));
            $this->success('操作成功！');
        }

    }
    /**
     * 审核拒绝
     */
    public function auth_reject(){
        if(request()->isPost()){
            $id = input('id');
            $info = Db::name('supper')->where(['id'=>$id])->find();
            if($info['status']!=0){
                $this->error('不支持该操作');
            }
            Db::startTrans();
            try{
                Db::name('supper')->where(['id'=>$id])->update([
                    'status'=>2,
                    'notice'=>input('content')
                ]);
                Db::commit();
                $this->success('操作成功');
            }catch (Exception $exception){
                Db::rollback();
                $this->error('失败'.$exception->getMessage());
            }

        }
    }
    
    public function auth_gps(){
        if(request()->isPost()){
            $id = input('id');
            $info = Db::name('supper')->where(['id'=>$id])->find();
            Db::startTrans();
            try{
                Db::name('supper')->where(['id'=>$id])->update([
                    'notice'=>input('content')
                ]);
                Db::commit();
                $this->success('操作成功');
            }catch (Exception $exception){
                Db::rollback();
                $this->error('失败'.$exception->getMessage());
            }

        }
    }
    
    public function supper_del(){
        Db::name('supper')->where([['id','in',input('post.id')]])->delete();
        $this->success('删除成功');
    }
    /**
     * 店铺编辑
     */
    public function alliance(){
        $where = [];
        if(input('title')) {
            $where[] = ['s.title','like','%'.input('title').'%'];
        }
        if(input('tel')!='') {
            $where[] = ['s.tel','eq',input('tel')];
        }
        $this->_query("alliance s")->field('s.*,m.id as mid,m.tel as mtel')->leftJoin('member m','m.id=s.member_id')->where($where)->order('s.id desc')->page();
    }
    /**
     * 审核通过
     */
    public function adopt(){
        if(request()->isPost()){
            $id = input('id');
            $info = Db::name('alliance')->where(['id'=>$id])->find();
            if($info['status']!=0){
                $this->error('不支持该操作');
            }
            Db::startTrans();
            try{
                Db::name('alliance')->where(['id'=>$id])->update([
                    'status'=>1
                ]);
                Db::name('member')->where(['id'=>$info['member_id']])->update([
                    'vip_level'=>4
                ]);
                Db::name('vip_log')->insert([
                    'member_id'=>$info['member_id'],
                    'vip_level'=>4
                ]);
                Db::commit();
                $this->success('操作成功');
            }catch (Exception $exception){
                Db::rollback();
                $this->error('失败'.$exception->getMessage());
            }
        }
    }

    /**
     * 审核拒绝
     */
    public function reject(){
        if(request()->isPost()){
            $id = input('id');
            $info = Db::name('alliance')->where(['id'=>$id])->find();
            if($info['status']!=0){
                $this->error('不支持该操作');
            }
            Db::startTrans();
            try{
                Db::name('alliance')->where(['id'=>$id])->update([
                    'status'=>2,
                    'notice'=>input('content')
                ]);
                Db::commit();
                $this->success('操作成功');
            }catch (Exception $exception){
                Db::rollback();
                $this->error('失败'.$exception->getMessage());
            }

        }
    }
}