<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2019/7/9
 * Time: 15:40
 */

namespace app\index\controller;
use think\Db;
class Cart extends Base
{
    /**
     * 购物车列表
     * @action
     * */
    public function index()
    {
        $user_id = $this->member_id;
        $cartList = Db::name('mall_shopcar c')->field('c.id,c.product_id,c.spec_id,p.imglogo,number,title,po.price,po.name spec_name,c.spec_id,c.is_selected')->join('mall_product p','c.product_id=p.id')->leftJoin('mall_product_spec po',' po.spec_id=c.spec_id')->where(['c.member_id' => $user_id, 'p.status' => 1])->order('c.id desc')->select();
        $cartList1 = [];
        if($cartList){
            foreach ($cartList as $v){
                if($v['spec_id']){
                    $specinfo = Db::name('mall_product_spec')->where(['spec_id'=>$v['spec_id']])->find();
                    if(!$specinfo){
                        Db::name('mall_shopcar')->where(['id'=>$v['id']])->delete();
                        continue;
                    }
                    $v['stock'] = $specinfo['stock'];
                }
                //优化后代码
                //$v['imglogo'] = str_replace('https://shop.', 'http://load.', $v['imglogo']);
                $cartList1[] = $v;
            }
        }
        if($cartList){
            $this->response($cartList1,true);
        }else{
            $this->response('暂无相关数据');
        }
    }

    /**
     * 购物车商品是否选中
     * @return void
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function is_selected(){
        $cart_id = input('id');
        $type = input('type');
        $user_id = $this->member_id;
        if(!in_array($type,[0,1])){
            $this->response('参数错误');
        }
        $changeRes = Db::name('mall_shopcar')->where(['member_id' => $user_id, 'id' => $cart_id])->update(['is_selected' => $type]);
        if ($changeRes or $changeRes === 0){
            $this->response('保存成功',true);
        }else{
            $this->response('保存失败,请稍后再试！');
        }
    }
    
    private function fileterInput($value)
    {
        $value = trim($value);
        // 1. 去除 SQL 关键字
        $sql_keywords = [
            'select', 'insert', 'update', 'delete', 'drop', 'create', 'alter', 'truncate',
            'union', 'show', 'declare', 'exec', 'or', 'and', 'like', 'into', 'load', 'outfile',
            'table', 'database', 'case', 'when', 'then', 'group', 'by', 'having', 'limit', 'order',
            'join', 'on', 'inner', 'left', 'right', 'outer', 'cross', 'distinct', 'set', 'null'
        ];
        $input_lower = strtolower($value);

        foreach ($sql_keywords as $keyword) {
            $input_lower = str_replace($keyword, '', $input_lower);
        }

        // 3. 删除所有可能的恶意字符（如单引号、双引号、分号、注释符号等）
        $input_cleaned = preg_replace('/[\'"%;#()^&<>*\/]/', '', $input_lower);

        // 4. 删除 SQL 注释符号（-- 或 /* */）
        $input_cleaned = preg_replace('/(--|\/*\*.*?\*\/)/', '', $input_cleaned);

        // 5. 删除 SQL 函数 (例如: md5())
        // 这里通过更强的正则过滤掉括号中的函数（md5(1)）和类似的内容
        $input_cleaned = preg_replace('/\(\w+\s*\(\d+\)\)/', '', $input_cleaned); // 移除类似 md5(1)

        $input_cleaned = str_replace(',', '', $input_cleaned);

        // 7. 去除多余的符号和空格，特别是多余的斜杠和破坏性符号
        $input_cleaned = preg_replace('/[\/\-\_\*]+/', '', $input_cleaned);  // 去除多个破坏性符号
        $input_cleaned = preg_replace('/\s+/', '', $input_cleaned);  // 去除多余的空格
        return $input_cleaned;
    }
    
    /**
     * 添加到购物车
     * @action
     * */
    public function add()
    {
        $user_id = $this->member_id;
        #商品id
        $pid = input('get.goods_id');
        $pid = $this->fileterInput($pid);
        $spec_id = input('get.spec_id');
        $spec_id = $this->fileterInput($spec_id);
        //$nums = empty(input('get.number')) ? 1 : input('number');
        $nums = empty(input('get.number')) ? 1 : input('number', 'intval');
        $product_res = Db::name('mall_shopcar')->where(['member_id' => $user_id, 'product_id' => $pid, 'spec_id' => $spec_id])->value('id');
        $goods_type = Db::name('mall_product')->where(['id'=>$pid])->value('goods_type');
        if(!in_array($goods_type,[1,2])){
            $this->response('该商品不能加入购物车,请直接购买');
        }
        //$can_add = Db::name('mall_shopcar')->where("goods_type != {$goods_type} and member_id={$user_id}")->find();
        //$can_add =  Db::name('mall_shopcar')->where("goods_type",'<>',$goods_type)->where("member_id","=",$user_id)->find();
        $can_add =  Db::name('mall_shopcar c')->join('mall_product p', 'c.product_id=p.id')->where("p.status",'<>','0')->where("c.goods_type",'<>',$goods_type)->where("c.member_id","=",$user_id)->find();
        
        if($can_add){
            $this->response('您的购物车中存在'.config('goods_type')[$can_add['goods_type']].'商品，请先结算后再添加！');
        }
        if ($product_res) {
            Db::name('mall_shopcar')->where(['id' => $product_res])->update([
                'number'=>Db::raw('number+'.$nums)
            ]);
            $this->response('成功添加购物车',true);
        }
        $addcart_res = Db::name('mall_shopcar')->insert([
            'member_id' => $user_id,
            'product_id' => $pid,
            'number' => $nums,
            'spec_id' => $spec_id,
            'goods_type'=>$goods_type
        ]);
        if ($addcart_res){
            $this->response('成功添加购物车',true);
        }else{
            $this->response('添加失败,请稍后再试！');
        }
    }

    /**
     * 查询购物车数量
     * @action
     * */
    public function getCarNum()
    {
        $user_id = $this->member_id;
        $num = Db::name('mall_shopcar c')->join('mall_product p', 'c.product_id=p.id')->where(['c.member_id' => $user_id, 'p.status' => 1])->count();
        $this->response($num,true);
    }


    /**
     * 修改购物车数量
     * @action
     * */
    public function saveNum()
    {
        $cart_id = input('id');
        $user_id = $this->member_id;
        $number = input('num');
        $changeRes = Db::name('mall_shopcar')->where(['member_id' => $user_id, 'id' => $cart_id])->update(['number' => $number]);
        if ($changeRes or $changeRes === 0){
            $this->response('保存成功',true);
        }else{
            $this->response('保存失败,请稍后再试！');
        }
    }


    /**
     * 删除购物车
     * @action
     * */
    public function del()
    {
        $cart_id = input('id');
        $user_id = $this->member_id;
        $res = Db::name('mall_shopcar')->where(['member_id' => $user_id, 'id' => $cart_id])->delete();
        if ($res){
            $this->response('删除成功',true);
        }else{
            $this->response('删除失败,请稍后再试！');
        }
    }
}
