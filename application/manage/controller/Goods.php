<?php
/**
 * Created by PhpStorm.
 * User: L
 * Date: 2019/7/29
 * Time: 15:40
 */

namespace app\manage\controller;


use library\Controller;
use think\Db;
use think\Exception;

class Goods extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @Auther L
     * 商品列表
     */
    public function index(){
        $this->title = "商品列表";
        $where[] = ['p.is_del','eq',0];
        if (!empty($_GET['so'])){
            $where[]= ['p.title|p.id', 'like', "%{$_GET['so']}%"];
        }//模糊搜索
        if (!empty($_GET['prid'])){
            $where[] = ['d.prid','eq',$_GET['prid']];
            $ctype = Db::name('mall_protype')->where(['pid'=>$_GET['prid']])->column('name','id');
        }else{
            $ctype = [];
        }//一级分类
        if (!empty($_GET['cid'])){
            $where[] = ['d.cid','eq',$_GET['cid']];
        }//二级分类
        if (!empty($_GET['goods_type'])){
            $where[] = ['p.goods_type','eq',$_GET['goods_type']];
        }//二级分类
        if (!empty($_GET['supper_id'])){
            $where[] = ['p.supplier','eq',$_GET['supper_id']];
        }
        if (is_numeric(input('status'))){
            $where[] = ['p.status','eq',$_GET['status']];
        }//商品状态
        $goods_type = config('goods_type');
        $pro_type = Db::name('mall_protype')->where(['pid'=>0])->column('name','id');
        $supper_list  = Db::name('mall_product_supper')->order('id asc')->column('supper_name','id');
        $this->assign('proType',$pro_type);
        $this->assign('supper_list',$supper_list);
        $this->assign('goods_type',$goods_type);
        $this->assign('ctype',$ctype);
        $this->_query('mall_product p')->field('p.*,s.supper_name as supplier_name,c.name as c_name,spec.price as spec_price,spec.market_price as spec_market_price')->leftJoin('mall_dettype d',' p.id=d.pid')->leftJoin('mall_product_supper s','s.id = p.supplier')->leftJoin('mall_product_spec spec','spec.product_id = p.id')->leftJoin('mall_protype c','c.id = d.cid')->where($where)->group('p.id')->order('p.order_num desc,p.id desc')->page();
    }

    /**
     * @Auther L
     * 分类列表
     */
    public function classify(){
        $this->title = "分类列表";
        $so = input('so');
        $pid = input('pid');
        $where = [];
        if(!empty($so)){
            $where[0] = [
                'name' => ['like', "%{$so}%"],
                '_logic' => 'or'
            ];
            if(is_numeric($so)){
                $where[0]['id'] = $so;
            }
        }
        if($pid === '0' or !empty($pid)){
            $where['pid'] = $pid;
        }
        $type = Db::name('mall_protype')->where(['pid'=>0])->column('name','id');
        $this->assign('type',$type);
        $query = $this->_query("mall_protype");
        $query->order('sort desc')->where($where)->page();
    }
    /**
     * @Auther L
     * 商品编辑
     */
    public function goods_edit(){
        $this->title = '修改商品';
        $goods_id = input('id');
        $goods = Db::name('mall_product')->where(['id' => $goods_id])->find();
        $dettype = Db::name('mall_dettype')->where(['pid' => $goods_id])->select();
        $goods_spec = Db::name('mall_product_spec')->where(['product_id'=>$goods_id])->select();
        $suppers = Db::name('mall_product_supper')->order('id asc')->column('supper_name','id');
        $this->assign('suppers',$suppers);
        $this->assign([
            'goods' => $goods,
            'dettype' => $dettype,
            'protype' => $this->getProType(),
            'goods_spec'  =>$goods_spec,
        ]);
        $this->fetch();
    }

    /**
     * 数据处理
     * */
    
    public function dochange()
    {
        $data = input('');
        if(empty($data['title'])){
            return[
                'status' => false,
                'code' => '请输入商品名称',
            ];
        }
        if(empty($data['imglogo'])){
            return[
                'status' => false,
                'code' => '请至上传封面图',
            ];
        }
        if(empty($data['imgs'])){
            return[
                'status' => false,
                'code' => '请至至少上传一张轮播图',
            ];
        }
        if(empty($data['spec'])){
            return[
                'status' => false,
                'code' => '请至少添加一种规格属性',
            ];
        }
        if(empty($data['price'])){
            return[
                'status' => false,
                'code' => '请输入商品价格',
            ];
        }
        if(empty($data['supplier'])){
            return[
                'status' => false,
                'code' => '请输入商品供应商',
            ];
        }
        if(empty($data['goods_type'])){
            return[
                'status' => false,
                'code' => '请选择商品分区',
            ];
        }
        if(empty($data['details'])){
            return[
                'status' => false,
                'code' => '请输入商品详情',
            ];
        }
        if($data['goods_type'] == 5){
            $vip_goods = Db::name('mall_product')->where("id != {$data['id']} and goods_type=5")->find();
            if($vip_goods){
                return[
                    'status' => false,
                    'code' => '已经有会员区商品存在了！',
                ];
            }
        }else{
            $vip_goods = Db::name('mall_product')->where("id = {$data['id']} and goods_type=5")->find();
            if($vip_goods){
                return[
                    'status' => false,
                    'code' => '会员区商品不得改变商品分区！',
                ];
            }
        }
        try {
            //开启事物
            Db::startTrans();
            $goods_res =Db::name('mall_product')->where(['id' => $data['id']])->update([
                'title' => $data['title'],
                'imglogo' => $data['imglogo'],
                'imgs' => $data['imgs'],
                'price' => $data['price'],
                'details' =>$data['details'],
                'goods_type' =>$data['goods_type'],
                'stock' => 0,
                'supplier' => $data['supplier'],
                'sales' => $data['sales'],
                'status'=>$data['status'],
                'order_num'=>$data['order_num'],
            ]);
            //规格
            $spec = $data['spec'];
            if($spec){
                $market_price = $data['market_price'];
                $price = $data['price1'];
                $spec_stock = $data['spec_stock'];
//                $spec_sales = $data['spec_sales'];
                $spec_id = $data['spec_id'];
                $stock = 0;
               
                $s = '';
                foreach ($spec as $k=>$v){
                    $stock+= $spec_stock[$k];
                    if($spec_id[$k]==0){$xz = '新增规格';}else{$xz = $spec_id[$k];};
                    $s = ',规格名称:'.$v.',市场价:'.$market_price[$k].',售价:'.$price[$k].',库存:'.$spec_stock[$k].',规格ID:'.$xz.$s;
                    if($spec_id[$k]==0){
                        $specID[] = DB::name('mall_product_spec')->insert([
                            'name' => $v,
                            'market_price'=>$market_price[$k],
                            'price' => $price[$k],
                            'stock' => $spec_stock[$k],
//                            'sales' => $spec_sales[$k],
                            'product_id' => $data['id']
                        ]);
                    }else{
                        $specID[] = $spec_id[$k];
                        DB::name('mall_product_spec')->where(['spec_id'=>$spec_id[$k]])->update(
                            [
                                'name' => $v,
                                'market_price'=>$market_price[$k],
                                'price' => $price[$k],
                                'stock' => $spec_stock[$k],
//                                'sales' => $spec_sales[$k],
                            ]
                        );
                    }
                }
                if($specID){
                    DB::name('mall_product_spec')->where([['product_id','=',$data['id']],['spec_id','not in',$specID]])->delete();
                }
            }
            Db::name('mall_product')->where(['id'=>$data['id']])->update([
                'stock'=>$stock
            ]);
            //商品分类
            $goods_detType = array();
            $s_fenlei = '';
            foreach ($data['protype'] as $k => $v) {
                if (empty($v)) {
                    //没有选择该分类下的二级分类
                    Db::name('mall_dettype')->where(['prid' => $k, 'pid' => $data['id']])->delete();
                } else {
                    $s_fenlei =  '分类'. $v.$s_fenlei;
                    //查询该一级分类下是否有记录，没有则添加一条分类
                    $dettype = Db::name('mall_dettype')->field('id')->where(['prid' => $k, 'pid' => $data['id']])->find();
                    if ($dettype) {
                        Db::name('mall_dettype')->where(['prid' => $k, 'pid' => $data['id']])->update([
                            'cid' => $v
                        ]);
                    } else {
                        //添加记录
                        Db::name('mall_dettype')->insert(array(
                            'pid' => $data['id'],
                            'prid' => $k,
                            'cid' => $v
                        ));
                    }
                }
            }
            
            
            Db::name('log')->insert([
                    'level'=>'商品ID：'.$data['id'],
                    'type'=>51,
                    'msg'=>'操作角色ID：'.intval(session('user.id')).' [修改商品] '.'。标题：'.$data['title'].'。LOGO图：'.$data['imglogo'].'。轮播图：'.$data['imgs'].'。价格：'.$data['price'].'。详情' .$data['details'].'。商品类型' .$data['goods_type'].'。供应商：'.$data['supplier'].'。销量：'.$data['sales'].'。上架状态：'.$data['status'].'。排序：'.$data['order_num'].'。【规格】 '.$s.'。【分类】'.$s_fenlei,
                    'create_time'=>date('Y-m-d H:i:s')
            ]);
            
            
            //事物存储
            Db::commit();
            return ([
                'status' => true,
                'code' => '商品修改成功!'
            ]);
        } catch (\Exception $exception) {
           Db::rollback();
            //删除文成功文件
            return ([
                'status' => false,
                'code' => '商品修改失败,请稍后再试!',
                'result' => $exception->getMessage()
            ]);
        }

    }
     
    /* 
    public function dochange()
    {
        $data = input('');
        if(empty($data['title'])){
            return[
                'status' => false,
                'code' => '请输入商品名称',
            ];
        }
        if(empty($data['imglogo'])){
            return[
                'status' => false,
                'code' => '请至上传封面图',
            ];
        }
        if(empty($data['imgs'])){
            return[
                'status' => false,
                'code' => '请至至少上传一张轮播图',
            ];
        }
        if(empty($data['spec'])){
            return[
                'status' => false,
                'code' => '请至少添加一种规格属性',
            ];
        }
        if(empty($data['price'])){
            return[
                'status' => false,
                'code' => '请输入商品价格',
            ];
        }
        if(empty($data['supplier'])){
            return[
                'status' => false,
                'code' => '请输入商品供应商',
            ];
        }
        if(empty($data['goods_type'])){
            return[
                'status' => false,
                'code' => '请选择商品分区',
            ];
        }
        if(empty($data['details'])){
            return[
                'status' => false,
                'code' => '请输入商品详情',
            ];
        }
        if($data['goods_type'] == 5){
            $vip_goods = Db::name('mall_product')->where("id != {$data['id']} and goods_type=5")->find();
            if($vip_goods){
                return[
                    'status' => false,
                    'code' => '已经有会员区商品存在了！',
                ];
            }
        }else{
            $vip_goods = Db::name('mall_product')->where("id = {$data['id']} and goods_type=5")->find();
            if($vip_goods){
                return[
                    'status' => false,
                    'code' => '会员区商品不得改变商品分区！',
                ];
            }
        }
        try {
            //开启事物
            Db::startTrans();
            $goods_res =Db::name('mall_product')->where(['id' => $data['id']])->update([
                'title' => $data['title'],
                'imglogo' => $data['imglogo'],
                'imgs' => $data['imgs'],
                'price' => $data['price'],
                'details' =>$data['details'],
                'goods_type' =>$data['goods_type'],
                'stock' => 0,
                'supplier' => $data['supplier'],
                'sales' => $data['sales'],
                'status'=>$data['status'],
                'order_num'=>$data['order_num'],
            ]);
            //规格
            $spec = $data['spec'];
            if($spec){
                $market_price = $data['market_price'];
                $price = $data['price1'];
                $spec_stock = $data['spec_stock'];
//                $spec_sales = $data['spec_sales'];
                $spec_id = $data['spec_id'];
                $stock = 0;
                foreach ($spec as $k=>$v){
                    $stock+= $spec_stock[$k];
                    if($spec_id[$k]==0){
                        $specID[] = DB::name('mall_product_spec')->insert([
                            'name' => $v,
                            'market_price'=>$market_price[$k],
                            'price' => $price[$k],
                            'stock' => $spec_stock[$k],
//                            'sales' => $spec_sales[$k],
                            'product_id' => $data['id']
                        ]);
                    }else{
                        $specID[] = $spec_id[$k];
                        DB::name('mall_product_spec')->where(['spec_id'=>$spec_id[$k]])->update(
                            [
                                'name' => $v,
                                'market_price'=>$market_price[$k],
                                'price' => $price[$k],
                                'stock' => $spec_stock[$k],
//                                'sales' => $spec_sales[$k],
                            ]
                        );
                    }
                }
                if($specID){
                    DB::name('mall_product_spec')->where([['product_id','=',$data['id']],['spec_id','not in',$specID]])->delete();
                }
            }
            Db::name('mall_product')->where(['id'=>$data['id']])->update([
                'stock'=>$stock
            ]);
            //商品分类
            $goods_detType = array();
            foreach ($data['protype'] as $k => $v) {
                if (empty($v)) {
                    //没有选择该分类下的二级分类
                    Db::name('mall_dettype')->where(['prid' => $k, 'pid' => $data['id']])->delete();
                } else {
                    //查询该一级分类下是否有记录，没有则添加一条分类
                    $dettype = Db::name('mall_dettype')->field('id')->where(['prid' => $k, 'pid' => $data['id']])->find();
                    if ($dettype) {
                        Db::name('mall_dettype')->where(['prid' => $k, 'pid' => $data['id']])->update([
                            'cid' => $v
                        ]);
                    } else {
                        //添加记录
                        Db::name('mall_dettype')->insert(array(
                            'pid' => $data['id'],
                            'prid' => $k,
                            'cid' => $v
                        ));
                    }
                }
            }
            //事物存储
            Db::commit();
            return ([
                'status' => true,
                'code' => '商品修改成功!'
            ]);
        } catch (\Exception $exception) {
           Db::rollback();
            //删除文成功文件
            return ([
                'status' => false,
                'code' => '商品修改失败,请稍后再试!',
                'result' => $exception->getMessage()
            ]);
        }

    }
    */
    /**
     * @Auther L
     * 商品添加
     */
    public function add(){
    $this->applyCsrfToken();
    $type = $this->getProType();
    $this->assign('type',$type);
    $suppers = Db::name('mall_product_supper')->order('id asc')->column('supper_name','id');
    $this->assign('suppers',$suppers);
    $this->fetch();
    }

    /**
     * @Auther L
     * 商品上下架
     */
    public function get_status(){
        $id = input('id');
        $type = input('type');
        if($type == 'true'){
            $status = 1;
            $code = '上架成功';
        }else{
            $status = 0;
            $code = '下架成功';
        }
        Db::name('mall_product')->where(['id'=>$id])->update([
            'status'=>$status
        ]);
        return[
            'status' => true,
            'msg' => $code,
        ];
    }
    /**
     * @Auther L
     * 执行商品添加
     */
    
    public function doadd(){
        $data = input('');
        if(empty($data['title'])){
            return[
                'status' => false,
                'code' => '请输入商品名称',
            ];
        }
        if(empty($data['imglogo'])){
            return[
                'status' => false,
                'code' => '请至上传封面图',
            ];
        }
        if(empty($data['imgs'])){
            return[
                'status' => false,
                'code' => '请至至少上传一张轮播图',
            ];
        }
        if(empty($data['spec'])){
            return[
                'status' => false,
                'code' => '请至少添加一种规格属性',
            ];
        }
        if(empty($data['price'])){
            return[
                'status' => false,
                'code' => '请输入商品价格',
            ];
        }
        if(empty($data['supplier'])){
            return[
                'status' => false,
                'code' => '请输入商品供应商',
            ];
        }
        if(empty($data['goods_type'])){
            return[
                'status' => false,
                'code' => '请选择商品分区',
            ];
        }
        if(empty($data['content'])){
            return[
                'status' => false,
                'code' => '请输入商品详情',
            ];
        }
        if($data['goods_type'] == 5){
            $vip_goods = Db::name('mall_product')->where("goods_type=5")->find();
            if($vip_goods){
                return[
                    'status' => false,
                    'code' => '已经有会员区商品存在了！',
                ];
            }
        }
        try {
            Db::startTrans();
            $goods_res = Db::name('mall_product')->insert([
                'title' => $data['title'],
                'imglogo' => $data['imglogo'],
                'imgs' => $data['imgs'],
                'price' => $data['price'],
                'details' =>$data['content'],
                'stock' => 0,
                'supplier' =>$data['supplier'],
                'sales' => $data['sales'],
                'order_num'=>$data['order_num'],
                'goods_type' => $data['goods_type']
            ]);

            //商品分类
            $goods_detType = array();
            $s_fenlei = '';
            foreach ($data['protype'] as $k => $v) {
                
                if(!empty($v)){$s_fenlei =  '分类'. $v.$s_fenlei;}
                if (empty($v))
                    continue;
                $goods_detType[] = array(
                    'pid' => $goods_res,
                    'prid' => $k,
                    'cid' => $v
                );
            }
            //添加分类
            Db::name('mall_dettype')->insertAll($goods_detType);
            if(!empty($data['spec'])){
                $spec = $data['spec'];
                $market_price = $data['market_price'];
                $price = $data['price1'];
                $spec_stock = $data['spec_stock'];
//                $spec_sales = $data['spec_sales'];
                $stock = 0;
                $s = '';
                foreach ($spec as $k=>$v){
                    $s = ',规格名称:'.$v.',市场价:'.$market_price[$k].',售价:'.$price[$k].',库存:'.$spec_stock[$k].$s;
                   
                    $stock+=$spec_stock[$k];
                    $specInsertData[] = [
                        'name' => $v,
                        'market_price'=>$market_price[$k],
                        'price' => $price[$k],
                        'stock' => $spec_stock[$k],
//                        'sales' => $spec_sales[$k],
                        'product_id' => $goods_res
                    ];
                }
                Db::name('mall_product')->where(['id'=>$goods_res])->update([
                    'stock'=>$stock
                ]);
                Db::name('mall_product_spec')->insertAll($specInsertData);
            }

            Db::name('log')->insert([
                    'level'=>'新增商品',
                    'type'=>50,
                    'msg'=>'操作角色ID：'.intval(session('user.id')).' [新增商品] '.'。标题：'.$data['title'].'。LOGO图：'.$data['imglogo'].'。轮播图：'.$data['imgs'].'。价格：'.$data['price'].'。详情' .$data['content'].'。商品类型' .$data['goods_type'].'。供应商：'.$data['supplier'].'。销量：'.$data['sales'].'。排序：'.$data['order_num'].'。【规格】 '.$s.'。【分类】'.$s_fenlei,
                    'create_time'=>date('Y-m-d H:i:s')
            ]);

            //事物存储
            Db::commit();
            return [
                'status' => true,
                'code' => '商品添加成功!'
            ];
        } catch (\Exception $exception) {
            DB::rollback();
            //删除文成功文件
            return[
                'status' => false,
                'code' => '商品添加失败,请稍后再试!(原因：'.$exception->getMessage().')',
                'result' => $exception->getMessage()
            ];
        }
    }
     
    /*
    public function doadd(){
        $data = input('');
        if(empty($data['title'])){
            return[
                'status' => false,
                'code' => '请输入商品名称',
            ];
        }
        if(empty($data['imglogo'])){
            return[
                'status' => false,
                'code' => '请至上传封面图',
            ];
        }
        if(empty($data['imgs'])){
            return[
                'status' => false,
                'code' => '请至至少上传一张轮播图',
            ];
        }
        if(empty($data['spec'])){
            return[
                'status' => false,
                'code' => '请至少添加一种规格属性',
            ];
        }
        if(empty($data['price'])){
            return[
                'status' => false,
                'code' => '请输入商品价格',
            ];
        }
        if(empty($data['supplier'])){
            return[
                'status' => false,
                'code' => '请输入商品供应商',
            ];
        }
        if(empty($data['goods_type'])){
            return[
                'status' => false,
                'code' => '请选择商品分区',
            ];
        }
        if(empty($data['content'])){
            return[
                'status' => false,
                'code' => '请输入商品详情',
            ];
        }
        if($data['goods_type'] == 5){
            $vip_goods = Db::name('mall_product')->where("goods_type=5")->find();
            if($vip_goods){
                return[
                    'status' => false,
                    'code' => '已经有会员区商品存在了！',
                ];
            }
        }
        try {
            Db::startTrans();
            $goods_res = Db::name('mall_product')->insert([
                'title' => $data['title'],
                'imglogo' => $data['imglogo'],
                'imgs' => $data['imgs'],
                'price' => $data['price'],
                'details' =>$data['content'],
                'stock' => 0,
                'supplier' =>$data['supplier'],
                'sales' => $data['sales'],
                'order_num'=>$data['order_num'],
                'goods_type' => $data['goods_type']
            ]);

            //商品分类
            $goods_detType = array();
            foreach ($data['protype'] as $k => $v) {
                if (empty($v))
                    continue;
                $goods_detType[] = array(
                    'pid' => $goods_res,
                    'prid' => $k,
                    'cid' => $v
                );
            }
            //添加分类
            Db::name('mall_dettype')->insertAll($goods_detType);
            if(!empty($data['spec'])){
                $spec = $data['spec'];
                $market_price = $data['market_price'];
                $price = $data['price1'];
                $spec_stock = $data['spec_stock'];
//                $spec_sales = $data['spec_sales'];
                $stock = 0;
                foreach ($spec as $k=>$v){
                    $stock+=$spec_stock[$k];
                    $specInsertData[] = [
                        'name' => $v,
                        'market_price'=>$market_price[$k],
                        'price' => $price[$k],
                        'stock' => $spec_stock[$k],
//                        'sales' => $spec_sales[$k],
                        'product_id' => $goods_res
                    ];
                }
                Db::name('mall_product')->where(['id'=>$goods_res])->update([
                    'stock'=>$stock
                ]);
                Db::name('mall_product_spec')->insertAll($specInsertData);
            }

            //事物存储
            Db::commit();
            return [
                'status' => true,
                'code' => '商品添加成功!'
            ];
        } catch (\Exception $exception) {
            DB::rollback();
            //删除文成功文件
            return[
                'status' => false,
                'code' => '商品添加失败,请稍后再试!(原因：'.$exception->getMessage().')',
                'result' => $exception->getMessage()
            ];
        }
    }
    */
    /**
     * @Auther L
     * 删除分类
     */
    public function classify_del()
    {
        $id = input('id');
        Db::startTrans();
         $res = Db::name('mall_protype')->where(['id'=>$id])->delete();
         Db::name('mall_protype')->where(['pid'=>$id])->delete();
        if($res){
            Db::commit();
            return [
                'status' => true,
                'msg' => '删除成功'
            ];
        }else{
            Db::rollback();
            return [
                'status' => false,
                'msg' => '删除失败，请稍后再试'
            ];
        }
    }
    /**
     * @Auther L
     * 删除供应商
     */
    public function supper_del()
    {
        $id = input('id');
        Db::startTrans();
        $res = Db::name('mall_product_supper')->where(['id'=>$id])->delete();
        if($res){
            Db::commit();
            return [
                'status' => true,
                'msg' => '删除成功'
            ];
        }else{
            Db::rollback();
            return [
                'status' => false,
                'msg' => '删除失败，请稍后再试'
            ];
        }
    }

    public function classify_edit(){
        $this->title = "添加分类·";
        $this->applyCsrfToken();
        $type = Db::name('mall_protype')->where(['pid'=>0])->column('name','id');
        $this->assign('type',$type);
        $this->_form('mall_protype', 'classify_edit', 'id');
    }
    /**
     * @Auther L
     * 供应商列表
     */
    public function supper(){
        $this->title = "供应商列表";
        $query = $this->_query("mall_product_supper");
        $query->order('id desc')->page();
    }
    /**
     * @Auther L
     * 编辑供应商
     */
    public function supper_edit(){
        $this->title = "编辑供应商";
        $this->applyCsrfToken();
        $this->_form('mall_product_supper', 'supper_edit', 'id');
    }
    /**
     * 获取顶级分类接口
     * @print json
     * */
    public function getTypeJson()
    {
        $pid = input('pid');
        $types = Db::name('mall_protype')->field('id,name')->where(['pid'=>$pid])->select();
        return [
            'list'=>$types
        ];
    }

    /**
     * 查询分类
     * @param int $id
     * @param string $str
     * @param bool $recursive
     * @return array
     */
    static public function getType($id = 0,$recursive = true, $str = '－')
    {
        static $typeInfo;
        $types = Db::name('mall_protype')->where(['pid'=>$id])->select();
        foreach ($types as $v) {
            $typeInfo[] = array_merge($v, ['name' => ($id ? $str :'') . $v['name']]);
            $recursive and self::getType($v['id'],$recursive, $str.$str);
        }
        return $typeInfo;
    }
    /**
     * 删除商品
     * */
    public function del()
    {
        $pid = input('id');
        $goods = Db::name('mall_product')->where(['id'=>$pid])->find();
        if($goods['goods_type'] == 5){
            return [
                'status'=>false,
                'msg'=>'课件专区商品不允许删除！'
            ];
        }
        $delRes = Db::name('mall_product')->where(['id' => $pid])->update([
            'is_del'=>1
        ]);
        //删除所属分类
        if ($delRes) {
            return [
                'status'=>true,
                'msg'=>'删除成功'
            ];
        } else {
            return [
                'status'=>false,
                'msg'=>'删除失败,请稍后再试!'
            ];
        }
    }

    /**
     * 获取分类
     * @param int $pid
     * @return mixed
     */
    protected function getProType()
    {
        $protype = DB::name('mall_protype')->field('name,id,pid')->where(['pid' =>0])->select();
        foreach ($protype as $k => $v) {
            $protype[$k]['chi'] = DB::name('mall_protype')->field(['name,id,pid'])->where(['pid' => $v['id']])->select();
        }
        return $protype;
    }

}