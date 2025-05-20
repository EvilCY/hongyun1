<?php
/**
 * 网站配置
 */
namespace app\manage\controller;
use library\Controller;
use think\Db;
use think\Exception;

class System extends Controller
{
    public function message(){
        $this->title='系统消息';
        $this->_query('message')->order('id desc')->page();
    }
    public function message_add(){
        if(request()->isGet()){
            return $this->fetch();
        }else{
            $data = input('post.');
            if(!$data['title']) {
                $this->error('请输入标题');
            }
            if(!$data['descr']) {
                $this->error('请输入简介');
            }
            if(!$data['detail']) {
                $this->error('请输入内容');
            }
            try{
                Db::name('message')->insert([
                    'title'=>$data['title'],
                    'descr'=>$data['descr'],
                    'detail' => $data['detail']
                ]);
                $this->success('添加成功');
            }catch (Exception $exception){
                $this->error('添加失败'.$exception->getMessage());
            }
        }
    }
    public function message_del(){
        Db::name('message')->where([['id','in',input('post.id')]])->delete();
        $this->success('删除成功');
    }
    public function message_edit(){
        if(request()->isGet()){
            $info = Db::name('message')->where(['id'=>input('get.id')])->findOrFail();
            $this->assign('info',$info);
            return $this->fetch();
        }else{
            $data = input('post.');
            if(!$data['title']) {
                $this->error('请输入标题');
            }
            if(!$data['descr']){
                $this->error('请填写简介');
            }
            if(!$data['detail']){
                $this->error('请填写内容');
            }
            try{
                Db::name('message')->where(['id'=>$data['id']])->update([
                    'title'=>$data['title'],
                    'descr' => $data['descr'],
                    'detail' => $data['detail']
                ]);
                $this->success('添加成功');
            }catch (Exception $exception){
                $this->error('添加失败'.$exception->getMessage());
            }
        }
    }
    //配置
    public function config(){
        if(request()->isPost()){
            $data = input('post.');
            foreach ($data as $k => $v){
                $res[] = Db::name('config')->where(['key'=>$k])->update([
                    'val' => $v
                ]);
            }
            if(in_array(true,$res)){
                cache('web_conf',null);
            }
            return[
                'code' => 1,
                'info' => '更新成功',
            ];
        }else{
            $this->title = '配置信息';
            $info = Db::name('config')->order('id')->select();
            $this->assign('list',$info);
            return $this->fetch();
        }
    }
    //广告列表
    public function ads(){
        $this->title = '广告列表';
        $this->_query("ads")->order('id desc')->page();
    }
    public function ads_add(){
        if(request()->isGet()){
            $this->fetch();
        }else{
            $data = input();
            if(empty($data['imgurl'])){
                return[
                    'code' => 0,
                    'info' => '请上传图片',
                ];
            }
            if(empty($data['title'])){
                return[
                    'code' => 0,
                    'info' => '请填写标题',
                ];
            }
            $data['link'] = ($data['link']?:'#');
            Db::name('ads')->insert([
                'title'=>$data['title'],
                'imgurl' => $data['imgurl'],
                'link_type' => $data['link_type'],
                'type' => $data['type'],
                'link' => $data['link'],
                'order_num' => $data['order_num'],
                'status' => $data['status']
            ]);
            return [
                'code' => 1,
                'info' => '添加成功!'
            ];
        }
    }
    public function ads_edit(){
        if(request()->isGet()){
            $info = Db::name('ads')->where(['id'=>input('get.id')])->find();
            $this->assign('info',$info);
            $this->fetch();
        }else{
            $data = input();
            if(empty($data['imgurl'])){
                return[
                    'code' => 0,
                    'info' => '请上传图片',
                ];
            }
            if(empty($data['title'])){
                return[
                    'code' => 0,
                    'info' => '请填写标题',
                ];
            }
            $data['link'] = ($data['link']?:'#');
            Db::name('ads')->where(['id'=>$data['id']])->update([
                'title'=>$data['title'],
                'imgurl' => $data['imgurl'],
                'link_type' => $data['link_type'],
                'link' => $data['link'],
                'type' => $data['type'],
                'order_num' => $data['order_num'],
                'status' => $data['status']
            ]);
            return [
                'code' => 1,
                'info' => '更新成功!'
            ];
        }
    }
    public function ads_status(){
        $id = input('id');
        $type = input('type');
        if($type == 'true'){
            $status = 1;
        }else{
            $status = 0;
        }
        Db::name('ads')->where(['id'=>$id])->update([
            'status'=>$status
        ]);
        return[
            'code' => 1,
            'info' => '操作成功',
        ];
    }
    public function ads_del(){
        $id = input('post.id');
        Db::name('ads')->where(['id'=>$id])->delete();
        return[
            'code' => 1,
            'info' => '操作成功',
        ];
    }
    //资讯信息
    public function news(){
        $where = [];
        if(input('title')) {
            $where[] = ['title|descr','like','%'.input('title').'%'];
        }
        $this->_query("news")->where($where)->order('id desc')->page();
    }
    public function news_del(){
        $id = input('post.id');
        Db::name('news')->where(['id'=>$id])->delete();
        return[
            'code' => 1,
            'info' => '操作成功',
        ];
    }
    public function news_status(){
        $id = input('id');
        $type = input('type');
        if($type == 'true'){
            $status = 1;
        }else{
            $status = 0;
        }
        Db::name('news')->where(['id'=>$id])->update([
            'status'=>$status
        ]);
        return[
            'code' => 1,
            'info' => '操作成功',
        ];
    }
    public function news_edit(){
        if(request()->isGet()){
            $this->title = '传统文化修改';
            $info = Db::name('news')->where(['id'=>input('get.id')])->find();
            $this->assign('info',$info);
            $this->fetch();
        }else{
            $data = input();
            if(empty($data['title'])){
                return[
                    'code' => 0,
                    'info' => '请填写昵称',
                ];
            }
            if(empty($data['imglogo'])){
                return[
                    'code' => 0,
                    'info' => '请上传头像',
                ];
            }
            if(empty($data['descr'])){
                return[
                    'code' => 0,
                    'info' => '请输入简介或描述',
                ];
            }
            if(empty($data['imgs'])){
                return[
                    'code' => 0,
                    'info' => '请至少上传一张图片',
                ];
            }
            Db::name('news')->where(['id'=>$data['id']])->update([
                'title' => $data['title'],
                'descr' => $data['descr'],
                'imglogo' => $data['imglogo'],
                'imgs' => $data['imgs'],
                'order_num' => $data['order_num'],
                'status' => $data['status']
            ]);
            return [
                'code' => 1,
                'info' => '更新成功!'
            ];
        }
    }
    public function news_add(){
        if(request()->isGet()){
            $this->title = '传统文化添加';
            $this->fetch();
        }else{
            $data = input();
            if(empty($data['title'])){
                return[
                    'code' => 0,
                    'info' => '请填写昵称',
                ];
            }
            if(empty($data['imglogo'])){
                return[
                    'code' => 0,
                    'info' => '请上传头像',
                ];
            }
            if(empty($data['descr'])){
                return[
                    'code' => 0,
                    'info' => '请输入简介或描述',
                ];
            }
            if(empty($data['imgs'])){
                return[
                    'code' => 0,
                    'info' => '请至少上传一张图片',
                ];
            }
            Db::name('news')->insert([
                'title' => $data['title'],
                'descr' => $data['descr'],
                'imglogo' => $data['imglogo'],
                'imgs' => $data['imgs'],
                'order_num' => $data['order_num'],
                'status' => $data['status']
            ]);
            return [
                'code' => 1,
                'info' => '添加成功!'
            ];
        }
    }
}