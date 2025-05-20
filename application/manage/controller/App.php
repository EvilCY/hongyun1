<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2020/9/7
 * Time: 14:09
 */

namespace app\manage\controller;


use library\Controller;
use think\Db;

class App extends Controller
{
    /**
     * 版本历史
     */
    public function index(){
        $this->title = '版本历史';
        $this->_query('app_version')->order('id desc')->page();
    }

    /**
     * 发布版本
     */
    public function version_add(){
        if(request()->isGet()){
            $this->fetch();
        }else{
            $data = input();
            if(!$data['version']){
                $this->error('请填写版本号');
            }
            if(!$data['title']){
                $this->error('请填写标题');
            }
            if(!$data['descr']){
                $this->error('请填写简介');
            }
            if(!$data['dlink_android']){
                $this->error('请填写安卓下载链接');
            }
            if(!$data['dlink_ios']){
                $this->error('请填写IOS下载链接');
            }
            Db::name('app_version')->insert([
                'version' => $data['version'],
                'title' => $data['title'],
                'descr' => $data['descr'],
                'dlink_android' => $data['dlink_android'],
                'dlink_ios' => $data['dlink_ios'],
                'is_force' => $data['is_force'],
                'status' => $data['status']
            ]);
            if($data['status']==1){
                cache('app_version',null);
                $this->success('发布成功');
            }else{
                $this->success('添加成功');
            }
        }
    }

    /**
     * 编辑版本
     */
    public function version_edit(){
        if(request()->isGet()){
            $info = Db::name('app_version')->where('id',input('id'))->findOrFail();
            $this->assign('info',$info);
            $this->fetch();
        }else{
            $data = input();
            if(!$data['version']){
                $this->error('请填写版本号');
            }

            if(!$data['title']){
                $this->error('请填写标题');
            }
            if(!$data['descr']){
                $this->error('请填写简介');
            }
            if(!$data['dlink_android']){
                $this->error('请填写安卓下载链接');
            }
            if(!$data['dlink_ios']){
                $this->error('请填写IOS下载链接');
            }
            Db::name('app_version')->where('id',$data['id'])->update([
                'version' => $data['version'],
                'title' => $data['title'],
                'descr' => $data['descr'],
                'dlink_android' => $data['dlink_android'],
                'dlink_ios' => $data['dlink_ios'],
                'is_force' => $data['is_force'],
                'status' => $data['status']
            ]);
            cache('app_version',null);
            $this->success('操作成功');
        }
    }
    public function version_del(){
        $id = input('post.id');
        Db::name('app_version')->where(['id'=>$id])->delete();
        $this->success('操作成功');
    }
}