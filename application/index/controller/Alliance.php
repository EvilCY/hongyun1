<?php

namespace app\index\controller;

use think\Db;

class Alliance extends Base
{
    /**
     * 合作商申请
     */
    public function supper_apply(){
        if (request()->isPost()) {
            $this->member_oplimit();
            if(!$this->userinfo['is_vip']){
                $this->response('请购买新人福包，升级为正式会员',false,299);
            }
            $data  = input('post.');
            $info = Db::name('supper')->where(['member_id'=>$this->member_id,'type'=>1])->find();
            if($info){
                if($info['status'] == 0){
                    $this->response('请勿重复申请');
                }
            }
            if($info){
                if($info['status'] == 1){
                    $this->response('您已经是平台合作商了，请勿重复申请');
                }
            }
            if(!$data['name']){
                $this->response('请填写联系人');
            }
            if(!$data['tel']){
                $this->response('请填写联系电话');
            }
            if(!$data['product_type']){
                $this->response('请填写主营产品');
            }
            if(!$data['lng']){
                $this->response('请选择定位');
            }
            if(!$data['lat']){
                $this->response('请选择定位');
            }
            if(!$data['address']){
                $this->response('请填写详细地址');
            }
            if(!$data['img']){
                $this->response('请上传商户头像');
            }
            if(!$data['title']){
                $this->response('请填写商户名称');
            }
            $data['member_id'] = $this->member_id;
            $data['type'] = 1;
            try{
                if($info){
                    $data['status'] = 0;
                    Db::name('supper')->where(['id'=>$info['id']])->update($data);
                }else{
                    Db::name('supper')->insert($data);
                }
                $this->response('提交成功,请等待审核',true);
            }catch (\Exception $exception){
                $this->response('失败，请稍后再试');
            }

        }
    }
    /**
     * 合作商申请
     */
    public function alliance_apply(){
        if (request()->isPost()) {
            $this->member_oplimit();
            if(!$this->userinfo['is_vip']){
                $this->response('请购买新人福包，升级为正式会员',false,299);
            }
            $data  = input('post.');
            $info = Db::name('supper')->where(['member_id'=>$this->member_id,'type'=>2])->find();
            if($info){
                if($info['status'] == 0){
                    $this->response('请勿重复申请');
                }
            }
            if($info){
                if($info['status'] == 1){
                    $this->response('您已经是平台合作商了，请勿重复申请');
                }
            }
            if(!$data['name']){
                $this->response('请填写联系人');
            }
            if(!$data['tel']){
                $this->response('请填写联系电话');
            }
            if(!$data['product_type']){
                $this->response('请填写主营产品');
            }
            if(!$data['lng']){
                $this->response('请选择定位');
            }
            if(!$data['lat']){
                $this->response('请选择定位');
            }
            if(!$data['address']){
                $this->response('请填写详细地址');
            }
            //if(!$data['img']){
            if (empty(trim($data['img'])) || preg_match('/[\x{4e00}-\x{9fa5}]/u', $data['img'])){
                $this->response('请上传商户头像,且图片不能大于3M');
            }
            if(!$data['title']){
                $this->response('请填写商户名称');
            }
            $data['member_id'] = $this->member_id;
            $data['type'] = 2;
            try{
                if($info){
                    $data['status'] = 0;
                    Db::name('supper')->where(['id'=>$info['id']])->update($data);
                }else{
                    Db::name('supper')->insert($data);
                }
                $this->response('提交成功,请等待审核',true);
            }catch (\Exception $exception){
                $this->response('失败，请稍后再试');
            }

        }
    }
    /**
     * 合作商申请首页
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function supper_index(){
        $status = 3;
        $supper = Db::name('supper')->where(['member_id'=>$this->member_id,'type'=>1])->find();
        $msg = '待申请';
        if($supper){
            if($supper['status'] == 1){
                $status = 1;
                $msg='恭喜您审核通过！';
            }
            if($supper['status'] == 2){
                $status = 2;
                $msg = $supper['notice'];
            }
            if($supper['status'] == 0){
                $status = 0;
                $msg='审核中，别着急哦';
            }
        }
        $this->response([
            'status'=>$status,
            'msg'=>$msg,
            'info'=>$supper
        ],true);
    }
    /**
     * 加盟门店申请首页
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function alliance_index(){
        $status = 3;
        $supper = Db::name('supper')->where(['member_id'=>$this->member_id,'type'=>2])->find();
        $msg = '待申请';
        if($supper){
            if($supper['status'] == 1){
                $status = 1;
                $msg='恭喜您审核通过！';
            }
            if($supper['status'] == 2){
                $status = 2;
                $msg = $supper['notice'];
            }
            if($supper['status'] == 0){
                $status = 0;
                $msg='审核中，别着急哦';
            }
        }
        $this->response([
            'status'=>$status,
            'msg'=>$msg,
            'info'=>$supper
        ],true);
    }
}