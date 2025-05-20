<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2019/7/9
 * Time: 15:40
 */

namespace app\index\controller;
use think\Db;
class Mall extends Base
{
    protected $is_jump_login = false;
    /**
     * @Auther L
     * 获取顶级分类
     * @return array
     */
    public function get_classfly(){
        //查询顶级分类
        $classfly = Db::name('mall_protype')->where(['pid'=>0])->field('name,id,ico')->order('sort')->select();
        if ($classfly){
            foreach ($classfly as&$item){
                $item['class_list'] = Db::name('mall_protype')->where(['pid'=>$item['id']])->field('name,id,ico')->order('sort')->select();
            }
        }
        $this->response($classfly,true);
    }

    /**
     * 系统消息列表
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function message_list(){
        $page = input('get.page',1,'intval');
        $limit = 20;
        $list =  Db::name('message')->field('id,title,descr')->order('id desc')->limit(($page-1)*$limit,$limit)->select();
        $total = Db::name('message')->count('id');
        $this->response([
            'totalPage' => ceil($total/$limit),
            'list' => $list
        ],true);
    }

    /**
     * 系统消息详情
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function massage_detail(){
        $id  = input('id');
        $data = Db::name('message')->where(['id'=>$id])->find();
        $this->response($data,true);
    }

    /**
     * 99课件专区
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function courseware(){
        #商品详情
        $product = Db::name("mall_product")->where(['goods_type' => 5,'is_del'=>0])->find();
        $goods_spec = Db::name('mall_product_spec')->where(['product_id' => $product['id']])->select();
        $member_id = $this->checkLogin();
        $is_vip = Db::name('member')->where(['id'=>$member_id])->value('is_vip');
        $default_spec_id = 0;
        if(count($goods_spec)){
            $default_spec = $goods_spec[0];
            $product['default_spec_id'] = $default_spec['spec_id'];
            $product['spec_id'] = $goods_spec;
            $product['market_price'] = $default_spec['market_price'];
            $product['price'] = $default_spec['price'];
            $product['is_vip'] = $is_vip;
        }
        #商品轮播图片
        $this->response($product,true);
    }
    /**
     * @Auther L
     * 商城首页
     */
    public function index(){
        //首页banner
        $banner = Db::name('ads')->field('id,imgurl,link_type,link,title')->where(['type'=>3,'status'=>1])->order('order_num asc')->cache(60)->select();
        //首页图标
        $mall_ico = Db::name('ads')->field('id,imgurl,link_type,link,title')->where(['type'=>2,'status'=>1])->order('order_num asc')->cache(60)->select();
        //首页广告
        $mall_ads = Db::name('ads')->field('id,imgurl,link_type,link,title')->where(['type'=>1,'status'=>1])->order('order_num asc')->cache(1)->select();
        
        $member_id = $this->checkLogin();
        $is_vip = null;
        if($member_id){
            $is_vip = Db::name('member')->where(['id'=>$member_id])->value('is_vip');
            $is_psw_logo = Db::name('config')->where(['id'=>'41'])->value('val');
            $is_12sx_logo = Db::name('config')->where(['id'=>'42'])->value('val');
            //补丁
//            if($is_vip == '1'){
//                foreach ($mall_ads as &$ad) {
//                if ($ad['id'] == 2) {
//                    $ad['imgurl'] = $is_12sx_logo;
//                    $ad['link'] = '/pages/99kj/99kj?name=十二生肖臻品礼包区';
//                    $ad['title'] = '十二生肖臻品礼包区';
//                }}
//            }
            
            //补丁
            
            //修改密码补丁
            $msg_tag = '修改密码 ID：'.$member_id;
            $is_psw = Db::name('log')->where(['type'=>'102','msg'=>$msg_tag])->value('id');
            
            if ($is_psw == null) {
                // 定义要添加的新内容
            $newContent1 = [
                'id' => 4, // 或者设置一个合适的ID，如果ID是唯一的
                'imgurl' => $is_psw_logo,
                'link_type' => '0',
                'link' => '#',
                'title' => '红韵东方红商城'
            ];
            $newContent2 = [
                'id' => 5, // 或者设置一个合适的ID，如果ID是唯一的
                'imgurl' => $is_psw_logo,
                'link_type' => '0',
                'link' => '#',
                'title' => '红韵东方红商城'
            ];
            array_unshift($banner, $newContent1, $newContent2);
            } 
            //修改密码补丁
            
        }
        
        //普通区
        //$common_area = Db::name('mall_product')->field("id,title,imglogo,price,goods_type")->where("status=1 and is_del=0 and stock>0 and goods_type=1")->limit(2)->orderRand()->select();
        $common_area = Db::name('mall_product p')->field('p.id, p.title, p.imglogo, p.price, p.goods_type')->leftJoin('mall_dettype d', 'd.pid = p.id')->where([['d.prid','=',58],['p.status','=',1], ['p.stock','>',0],['p.is_del','=',0]])->limit(4)->orderRand()->cache(120)->select();
        if($common_area){
            foreach ($common_area as$k=>$item){
                $common_area[$k]['spec'] = Db::name('mall_product_spec')->where(['product_id' => $item['id']])->select();
            }
        }
        $message = Db::name('message')->field('id,title')->limit(3)->order('id desc')->select();
        //康养平价商店
        $choice = Db::name('mall_product p')->field('p.id, p.title, p.imglogo, p.price, p.goods_type')->leftJoin('mall_dettype d', 'd.pid = p.id')->where([['d.prid','=',57],['p.status','=',1], ['p.stock','>',0],['p.is_del','=',0]])->limit(4)->orderRand()->cache(120)->select(); //正常.prid','=',57
        if($choice){
            foreach ($choice as$k=>$item){
                $choice[$k]['spec'] = Db::name('mall_product_spec')->where(['product_id' => $item['id']])->select();
            }
        }
        
        ///*优化后代码
        function replaceImgLogoUrls(&$goodsList) {
            foreach ($goodsList as &$goods) {
                if (isset($goods['imglogo']) && is_string($goods['imglogo'])) {
                    $goods['imglogo'] = str_replace('https://shop.', 'http://load.', $goods['imglogo']);
                }
            }
        }
        // 对 common_area 和 choice 中的商品列表进行处理
        replaceImgLogoUrls($common_area);
        replaceImgLogoUrls($choice);
        //*/
        
        $this->response([
            'banner'=>$banner,
            'mall_ico'=>$mall_ico,
            'mall_ads'=>$mall_ads,
            'message'=>$message,
            'is_vip'=>$is_vip,
            'qr_code'=>self::$config['qr_code'],
            'goods_list'=>[
                'common_area'=>[
                    'title'=>'普通区',
                    'goods_type'=>1,
                    'goods_list'=>$common_area
                ],
                'choice'=>[
                    'title'=>'康养平价商店',
                    'goods_type'=>1,
                    'goods_list'=>$choice
                ]
            ]
        ],true);

    }
    /**
     * @Auther L
     * 获取商品
     * @return array
     */
    public function get_goods_list(){
        //获取分类ID
        $cid = input('cid');
        //$order_name = input('order_name',false);
        $order_type = input('order_type',false);
        $page = (int)input('page',false)?:1;
        $limit = (int)input('limit',false)?:30;
        //获取关键词
        $so = input('keyword');
        $goods_type = input('goods_type',false);
        $where = [['p.status','=',1], ['p.is_del','=',0]];//['p.stock','>',0],
        if (!empty($cid)){
            $where[] = ['d.cid|d.prid', '=', "{$cid}"];
        }
        $order = 'p.order_num desc,p.id desc';
        if($order_type){
            $order ='p.price '.$order_type.','.$order;
        }

        if (!empty($so)){
            $where[] = ['p.title', 'like', "%{$so}%"];
        }
        if (!empty($goods_type)){
            $where[] = ['p.goods_type', 'eq', $goods_type];
        }
        
        if (!empty($cid)){
            $product = Db::name('mall_product p')
            ->field('p.imglogo,p.id,p.title,p.price,p.goods_type')
            ->leftJoin('mall_dettype d',' p.id=d.pid')
            ->where($where)
            ->group('p.id')
            ->order($order)
            ->paginate(null, false, [
                'page' => $page,
                'list_rows' => $limit,
            ]);
        }else{
            $product = Db::name('mall_product p')
            ->field('p.imglogo,p.id,p.title,p.price,p.goods_type,CASE 
                         WHEN p.goods_type = 1 THEN "普通区" 
                         WHEN p.goods_type = 2 THEN "精品区" 
                         WHEN p.goods_type = 3 THEN "兑换区"
                         WHEN p.goods_type = 4 THEN "提货区"
                         WHEN p.goods_type = 5 THEN "新人区"
                         WHEN p.goods_type = 6 THEN "积分区"
                         ELSE "未知区" 
                     END AS class,CASE 
                         WHEN p.goods_type = 1 THEN "#ccc" 
                         WHEN p.goods_type = 2 THEN "#09f" 
                         WHEN p.goods_type = 3 THEN "#cc9756" 
                         WHEN p.goods_type = 4 THEN "#cc9756" 
                         WHEN p.goods_type = 5 THEN "#cc9756" 
                         WHEN p.goods_type = 6 THEN "#cc9756" 
                         ELSE "#cc9756" 
                     END AS color')
            ->leftJoin('mall_dettype d',' p.id=d.pid')
            ->where($where)
            ->group('p.id')
            ->order($order)
            ->paginate(null, false, [
                'page' => $page,
                'list_rows' => $limit,
            ]);
        }
        
        $items = $product->items();
        if($items){
            foreach ($items as $k=>$item){
                //优化后代码
                $items[$k]['imglogo'] = str_replace('https://shop.', 'http://load.', $item['imglogo']);
                //
                $items[$k]['spec'] = Db::name('mall_product_spec')->where('product_id', $item['id'])->cache(60)->select();
            }
        }
        $this->response([
            'total' => $product->total(),
            'items' => $items,
            'total_page' => $product->lastPage(),
            'cur_page' => $product->currentPage(),
            'page' => $page
        ],true);
    }
    
    
    
    
    
    

    /**
     * @Auther L
     * 获取商品详情
     * @return array
     */
    /* 优化前代码
    public function get_goods_detail()
    {
        $pid = input('goods_id');
        #商品详情
        $product = Db::name("mall_product")->where(['id' => $pid])->find();
        $goods_spec = Db::name('mall_product_spec')->where(['product_id' => $product['id']])->order('stock', 'desc')->select();
        $default_spec_id = 0;
        if(count($goods_spec)){
            $default_spec = $goods_spec[0];
            $product['default_spec_id'] = $default_spec['spec_id'];
            $product['spec_id'] = $goods_spec;
            $product['market_price'] = $default_spec['market_price'];
            $product['price'] = $default_spec['price'];
        }
        unset($product['supper_price']);
        #商品轮播图片
        $this->response($product,true);
    }
    */
    
    ///* 优化后代码
    public function get_goods_detail()
    {
        $pid = input('goods_id');
        # 商品详情
        $product = Db::name("mall_product")->where(['id' => $pid])->find();
        $goods_spec = Db::name('mall_product_spec')->where(['product_id' => $product['id']])->order('stock', 'desc')->select();
        $default_spec_id = 0;
    
        if(count($goods_spec)){
            $default_spec = $goods_spec[0];
            $product['default_spec_id'] = $default_spec['spec_id'];
            $product['spec_id'] = $goods_spec;
            $product['market_price'] = $default_spec['market_price'];
            $product['price'] = $default_spec['price'];
        }
    
        unset($product['supper_price']);
    
        # 获取screen_width参数
        $screen_width_param = input('screen_width');
        preg_match('/w_(\d+)/', $screen_width_param, $matches);
        $width_ = isset($matches[1]) ? intval($matches[1]) : 0; // 转换为整数
        $width_ = 395;
        # 替换product中的图片链接
        $image_keys = ['details', 'imglogo', 'imgs']; // 假设这些是需要替换的键
        foreach ($image_keys as $key) {
            if (isset($product[$key])) {
                // 替换 https://shop. 为 http://load.
                $product[$key] = str_replace('https://shop.', 'http://load.', $product[$key]);
                // 替换 .jpg 和 .JPG
                $product[$key] = str_replace(
                    ['.jpg', '.JPG'],
                    ['.jpg?image_process=resize,w_' . $width_*2, '.JPG?image_process=resize,w_' . $width_*2],
                    $product[$key]
                );
            }
        }
    
        # 商品轮播图片
        $this->response($product, true);
    }
    //*/



    //2025-02-12新增需求
    //选择套餐

    //套餐列表
    public function setMenu()
    {
        $member_id = $this->checkLogin();
        $is_vip = Db::name('member')->where(['id'=>$member_id])->value('is_vip');
        // if($is_vip == 1){//会员才会显示十二生肖，非会员显示A和B两个套餐的内容
        //     $data = Db::name('ads')->cache(60)->field('title,imgurl,link,link_type')->where(['type'=>13,'status'=>1])->order('order_num desc')->select();
        //     $this->response([
        //         'ads'=>$data
        //     ],true);
        // }else{
        //     $data = Db::name('ads')->cache(60)->field('title,imgurl,link,link_type')->where(['status'=>1])->whereIn('type',[10,11])->order('order_num desc')->select();
        //     $this->response([
        //         'ads'=>$data
        //     ],true);
        // }
         $data = Db::name('ads')->cache(60)->field('title,imgurl,link,link_type')->where(['status'=>1])->whereIn('type',[10,11,13])->order('order_num desc')->select();
            $this->response([
                'ads'=>$data
            ],true);
    }

    //新人福包B套餐背景
    public function coursewareBBg(){
        $product = Db::name("mall_product")->field('imglogo,id,title,price,goods_type')->where(['goods_type' => 7,'is_del'=>0])->select();
         foreach ($product as &$item){
            $goods_spec = Db::name('mall_product_spec')->where(['product_id' => $item['id']])->select();
            if(count($goods_spec)){
                $default_spec = $goods_spec[0];
                $item['default_spec_id'] = $default_spec['spec_id'];
                $item['spec_id'] = $goods_spec;
                $item['market_price'] = $default_spec['market_price'];
                $item['price'] = $default_spec['price'];
                // $item['is_vip'] = $is_vip;
            }
        }
        $data = Db::name('ads')->cache(60)->field('imgurl,link,link_type')->where(['type'=>12,'status'=>1])->order('order_num desc')->find();
        $this->response([
            'ads'=>$data,
            'goods'=>$product
        ],true);
    }

//     //新人福包B套餐
//     public function coursewareB()
//     {
//         $product = Db::name("mall_product")->field('imglogo,id,title,price,goods_type')->where(['goods_type' => 7,'is_del'=>0])->select();
//         $member_id = $this->checkLogin();
//         $is_vip = Db::name('member')->where(['id'=>$member_id])->value('is_vip');
// //        $default_spec_id = 0;
// //        foreach ($product as &$item){
// //            $goods_spec = Db::name('mall_product_spec')->where(['product_id' => $item['id']])->select();
// //            if(count($goods_spec)){
// //                $default_spec = $goods_spec[0];
// //                $item['default_spec_id'] = $default_spec['spec_id'];
// //                $item['spec_id'] = $goods_spec;
// //                $item['market_price'] = $default_spec['market_price'];
// //                $item['price'] = $default_spec['price'];
// //                $item['is_vip'] = $is_vip;
// //            }
// //        }
//         #商品轮播图片
//         $this->response($product,true);
//     }

    //十二生肖背景图及分类
    public function bronYearBg(){
        $goodsType = Db::name('mall_protype')->where(['pid'=>60])->field('name,id,ico')->order('sort')->select();
        $data = Db::name('ads')->cache(60)->field('imgurl,link,link_type')->where(['type'=>14,'status'=>1])->order('order_num desc')->find();
        $this->response([
            'ads'=>$data,
            'goodsType'=>$goodsType
        ],true);
    }

    //十二生肖商品列表
    public function bronYearGoodsList()
    {
        $data = Db::name('ads')->cache(60)->field('imgurl,link,link_type')->where(['type'=>15,'status'=>1])->order('order_num desc')->find();
        $cid = input('cid');
        // $page = (int)input('page',false)?:1;
        // $limit = (int)input('limit',false)?:30;
        $where = [['p.status','=',1], ['p.is_del','=',0]];//['p.stock','>',0],
        $where[] = ['d.cid', '=', $cid];
        $order = 'p.order_num desc,p.id desc';
        $product = Db::name('mall_product p')
            ->field('p.imglogo,p.id,p.title,p.price,p.goods_type')
            ->leftJoin('mall_dettype d',' p.id=d.pid')
            ->where($where)
            ->order($order)
            ->select();
        foreach ($product as &$item){
            $goods_spec = Db::name('mall_product_spec')->where(['product_id' => $item['id']])->select();
            if(count($goods_spec)){
                $default_spec = $goods_spec[0];
                $item['default_spec_id'] = $default_spec['spec_id'];
                $item['spec_id'] = $goods_spec;
                $item['market_price'] = $default_spec['market_price'];
                $item['price'] = $default_spec['price'];
                // $item['is_vip'] = $is_vip;
            }
        }
       $this->response([
            'ads'=>$data,
            'goods'=>$product
        ],true);
    }

}