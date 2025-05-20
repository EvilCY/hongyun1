<?php
/**
 * APP资源加载接口
 * User: Angerl
 * Date: 2020/4/14
 * Time: 11:53
 */

namespace app\index\controller;


use app\lib\Curl;
use think\Db;
use think\Exception;

class Souce extends Base
{
    protected $is_jump_login = false;
    /**
     * 版本更新
     */
    public function app_version(){
        $version = input('get.version');
        $platform = input('get.type',false,'intval');
        if(!in_array($platform,[1101,1102])){
            abort(404);
        }
        $data = Db::name('app_version')->cache('app_version',120)->where('status',1)->field('version,title,descr,dlink_android,dlink_ios,is_force')->order('id desc')->find();
        $data['downloadurl'] = ($platform==1102?$data['dlink_ios']:$data['dlink_android']);
        unset($data['dlink_android']);
        unset($data['dlink_ios']);
        return $this->response($data,version_compare($data['version'],$version,'>'));
    }
    /**
     * APP启动图
     */
    public function app_lodimg(){
        $data = Db::name('ads')->cache(60)->field('imgurl,link,link_type')->where(['type'=>4,'status'=>1])->order('order_num desc')->select();
        $this->response($data,true);
    }
    public function vip_member(){
        $data = Db::name('hy_config')->where(['key'=>'vip_member'])->value('val');
        $this->response($data,true);
    }
    public function customer(){
        $this->response(self::$config['qr_code'],true);
    }
    /**
     * 系统消息
     */
    public function app_notice(){
        $info = Db::name('app_notice')->field('imgurl,link_type,link')->cache('app_notice',120)->where('status',1)->find();
        if($info){
            $this->response($info,true);
        }else{
            $this->response('no');
        }
    }
    /**
     * 图片上传
     * @return void
     * @throws Exception
     * @throws \think\exception\PDOException
     */
    /*
    public function uploads(){
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('image');
        if ($file->checkExt('php')) {
            $this->response('可执行文件禁止上传到本地服务器');
        }
        if ($file->checkImg()&&!$file->checkSize(3145728)) {
            $this->response('文件上传类型受限,或文件大于3M！');
        }
        // 移动到框架应用根目录/public/uploads/ 目录下
        if($file){
            $info = $file->move('upload/user');
            if($info){
                $this->response($_SERVER['REQUEST_SCHEME'].'://'.$_SERVER["SERVER_NAME"].'/upload/user/'.date('Ymd').'/'.$info->getFilename(),true);
            }else{
                // 上传失败获取错误信息
                $this->response('上传失败');
            }
        }
    }
    */
    public function uploads(){
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('image');
        if (!$file->checkExt(['jpg', 'png'])) {
            $this->response('图片类型受限');
        }
        if (!$file->checkSize(2048576)) {
            $this->response('图片容量过大');
        }
        // 移动到框架应用根目录/public/uploads/ 目录下
        if($file){
            $info = $file->move('upload/user');
            if($info){
                $this->response($_SERVER['REQUEST_SCHEME'].'://'.$_SERVER["SERVER_NAME"].'/upload/user/'.date('Ymd').'/'.$info->getFilename(),true);
            }else{
                // 上传失败获取错误信息
                $this->response('上传失败');
            }
        }
    }

    /**
     *个人中心banner
     * @action
     */
    public function member_banner(){
        $data = Db::name('ads')->cache(60)->field('imgurl,link,link_type')->where(['type'=>5,'status'=>1])->order('order_num desc')->select();
        $this->response($data,true);
    }

    /**
     * 新人福包背景图
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function vip_member_banner(){
        $data = Db::name('ads')->cache(60)->field('imgurl,link,link_type')->where(['type'=>8,'status'=>1])->order('order_num desc')->find();
        $button = Db::name('ads')->cache(60)->field('imgurl,link,link_type')->where(['type'=>9,'status'=>1])->order('order_num desc')->find();
        $this->response([
            'ads'=>$data,
            'button'=>$button
        ],true);
    }
    /**
     *首页广告
     * @action
     */
    public function member_ads(){
        $data = Db::name('ads')->cache(60)->field('imgurl,link,link_type')->where(['type'=>6,'status'=>1])->order('order_num desc')->select();
        $this->response($data,true);
    }
    /**
     *本地生活
     * @action
     */
    public function local_life(){
        $lng = input( 'get.lng' );
        $lat = input( 'get.lat' );
//        $lng = '106.503043';
//        $lat = '29.601519';
        $page = input('get.page',1,'intval');
        $limit = 20;
        $where = [];
        $where[] = ['type','eq',1];
        $where[] = ['status','eq',1];
        $list =  Db::name('supper')->where($where)->field("sqrt( ( (({$lat}-lat)*PI()*12656*cos((({$lng}+lng)/2)*PI()/180)/180) * (({$lat}-lat)*PI()*12656*cos ((({$lng}+lng)/2)*PI()/180)/180) ) + ( (({$lng}-lng)*PI()*12656/180) * (({$lng}-lng)*PI()*12656/180) ) ) as distance,id,title,img,product_type,address")->order('distance desc')->limit(($page-1)*$limit,$limit)->select();
        if ($list){
            foreach ($list as &$item){
                $item['product_type'] = explode(',',$item['product_type']);
            }
        }
        $total = Db::name('supper')->where($where)->count('id');
        $this->response([
            'totalPage' => ceil($total/$limit),
            'list' => $list
            ],true);
    }

    public function hands_shop(){
        $lng = input( 'get.lng' );
        $lat = input( 'get.lat' );
        $page = input('get.page',1,'intval');
        $keyword = input('get.keyword');
        $limit = 999;
        $where = [];
        $where[] = ['type','eq',2];
        $where[] = ['status','eq',1];
        $list =  Db::name('supper')
            ->where($where)
            ->where(function ($query)use($keyword){
                if($keyword != ''){
                    $query->where('title','like','%'.$keyword.'%');
                }
            })
	->field("sqrt( ( (({$lat}-lat)*PI()*12656*cos((({$lng}+lng)/2)*PI()/180)/180) * (({$lat}-lat)*PI()*12656*cos ((({$lng}+lng)/2)*PI()/180)/180) ) + ( (({$lng}-lng)*PI()*12656/180) * (({$lng}-lng)*PI()*12656/180) ) ) as distance,id,title,img,address,pro_id,notice")
	->order('distance asc')
	->limit(($page-1)*$limit,$limit)
	->select();
        if($list){
            foreach ($list as $k=>$v){
                if($v['pro_id']){
                    $list[$k]['goods_list'] = Db::name('mall_product')->field('id,title,imglogo,price')->where("id in(".$v['pro_id'].")")->limit(2)->select();

                }else{
                    $list[$k]['goods_list'] = [];
                }
                unset($list[$k]['pro_id']);

            }

        }
        $total = Db::name('supper')->where($where)->count('id');
        $this->response([
            'totalPage' => ceil($total/$limit),
            'list' => $list
        ],true);
    }

    public function hands_info(){
        $lng = input( 'get.lng' );
        $lat = input( 'get.lat' );
        $shop_id  = input('get.id');
        $page = input('get.page',1,'intval');
        $limit = 20;
        $where = [];
        $where[] = ['id','eq',$shop_id];
        $where[] = ['type','eq',2];
        $where[] = ['status','eq',1];
        $shopinfo = Db::name('supper')->where($where)->field("sqrt( ( (({$lat}-lat)*PI()*12656*cos((({$lng}+lng)/2)*PI()/180)/180) * (({$lat}-lat)*PI()*12656*cos ((({$lng}+lng)/2)*PI()/180)/180) ) + ( (({$lng}-lng)*PI()*12656/180) * (({$lng}-lng)*PI()*12656/180) ) ) as distance,id,title,img,address,pro_id,notice")->find();
        $list = [];
        $total = 0;
        if($shopinfo['pro_id']){
            $list = Db::name('mall_product')->field("id,title,imglogo,price")->where("id in(".$shopinfo['pro_id'].")")->limit(($page-1)*$limit,$limit)->select();
            $total = Db::name('mall_product')->where("id in(".$shopinfo['pro_id'].")")->count('id');
            unset($shopinfo['pro_id']);
        }
        if($page == 1){
            $this->response([
                'totalPage' => ceil($total/$limit),
                'shopinfo'=>$shopinfo,
                'list' => $list
            ],true);
        }
        $this->response([
            'totalPage' => ceil($total/$limit),
            'list' => $list
        ],true);
    }

    /**
     * 平台资质
     */
    public function natural(){
        $data = Db::name('hy_config')->where(['key'=>'natural'])->value('val');
        $this->response($data,true);
    }
    //系统公告
    public function sys_notice(){
        $info = Db::name('sys_notice')->field('status,imgurl,link,link_type')->where('id',2)->find();
        if($info['status']){
            $this->response($info,true);
        }else{
            $this->response('no');
        }
    }

    /**
     * 传统文化列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function culture_list(){
        $page = input('get.page',1,'intval');
        $limit = 20;
        $list =  Db::name('news')->field('id,title,descr,imglogo,imgs,hits,like_count,create_time')->order('order_num desc')->limit(($page-1)*$limit,$limit)->select();
        $total = Db::name('news')->count('id');
        if($list){
            $this->member_id = $this->checkLogin();
            $lick_id = [];
            if($this->member_id){
                $lick_id = Db::name('news_zan')->where(['member_id'=>$this->member_id,'status'=>1])->column('news_id');
            }
            foreach ($list as &$item){
                $item['is_zan'] = 0;
                if($lick_id){
                    if(in_array($item['id'],$lick_id)){
                        $item['is_zan'] = 1;
                    }
                }
                $item['imgs'] = json_decode($item['imgs'],true);
                $item['comment_num'] = Db::name('news_comment')->where(['news_id'=>$item['id']])->count();
                $item['create_time'] = formateTimeAgo($item['create_time']);
            }
        }
        $this->response([
            'totalPage' => ceil($total/$limit),
            'list' => $list
        ],true);
    }

    /**
     * 传统文化详情
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function culture_detail(){
        $news_id = input('get.news_id',0,'intval');
        $data = Db::name('news')->where(['id'=>$news_id])->find();
        if($news_id){
            Db::name('news')->where(['id'=>$news_id])->setInc('hits');
        }
        if($data){
            $this->member_id = $this->checkLogin();
            $data['imgs'] = json_decode($data['imgs'],true);
            if($this->member_id){
                $lick_id = Db::name('news_zan')->where(['member_id'=>$this->member_id,'status'=>1,'news_id'=>$news_id])->find();
                $data['is_zan'] = $lick_id?1:0;
            }else{
                $data['is_zan'] = 0;
            }
        }
        $this->response($data,true);
    }

    /**
     * 传统文化评论列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function colture_comment(){
        $news_id = input('get.news_id',0,'intval');
        $page = input('get.page',1,'intval');
        $limit = 20;
        $data = Db::name('news_comment n')->field('m.nickname,m.headimg,n.content,n.create_time')->leftJoin('member m','m.id = n.member_id')->where(['n.news_id'=>$news_id])->limit(($page-1)*$limit,$limit)->order('n.id desc')->select();
        $total = Db::name('news_comment')->where(['news_id'=>$news_id])->count('id');
        if($data){
            foreach ($data as &$item){
                $item['create_time'] = formateTimeAgo($item['create_time']);
            }
        }
        $this->response([
            'totalPage' => ceil($total/$limit),
            'list' => $data
        ],true);
    }



}