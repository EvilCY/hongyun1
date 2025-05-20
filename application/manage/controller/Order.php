<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2019/7/9
 * Time: 15:40
 */

namespace app\manage\controller;
use app\lib\Csv;
use library\Controller;
use think\Db;
use think\Exception;
use ZipArchive;

class Order extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * @Auther L
     * 商品订单管理
     */
    public function index(){
        # 搜索条件
        $where = [];
        if (!empty($_GET['tell'])) {
            $where[] =['m.tel','eq',$_GET['tell']] ;
        }
        if (!empty($_GET['tel'])) {
            $where[] =['o.tel','eq',$_GET['tel']] ;
        }
        /*
        if (!empty($_GET['order_status'])) {
            $where[] =['o.order_status','eq',$_GET['order_status']] ;
        }
        */
        if (isset($_GET['order_status']) && $_GET['order_status'] != '') {
            
            if ($_GET['order_status'] == 9) {
            $where[] = ['o.order_status', 'in', [1,3]];
            }
            else if($_GET['order_status'] == 10){
            $where[] = ['o.order_status', 'in', [1,2,3]];
            }
            else{
            $where[] =['o.order_status','=',$_GET['order_status']] ;
            }
        }
        if (isset($_GET['is_off']) && $_GET['is_off'] != '') {
            $where[] =['o.is_off','eq',$_GET['is_off']] ;
        }
        if (isset($_GET['pay_type']) && $_GET['pay_type'] != '') {
            $where[] =['o.pay_type','eq',$_GET['pay_type']] ;
        }
        if (isset($_GET['tran_sn']) && $_GET['tran_sn'] != '') {
            $where[] =['o.tran_sn','eq',$_GET['tran_sn']] ;
        }
        if(session('user.authorize') == 8){
                $store_id = Db::name('mall_product_supper')->where(['supper_name'=>session('user.username')])->value('id');
                $where[] =['p.supplier','eq',$store_id] ;
        }else{
            if (!empty($_GET['store_id'])) {
                $where[] =['p.supplier','eq',$_GET['store_id']] ;
            }
        }

        if (!empty($_GET['goods_type'])) {
            $where[] =['o.goods_type','eq',$_GET['goods_type']] ;
        }
        if (!empty($_GET['goods_id'])) {
            $where[] =['o.goods_id','eq',$_GET['goods_id']] ;
        }
        if (!empty($_GET['so'])) {
            $where[]= ['o.order_id|o.order_sn|o.address', 'like', "%{$_GET['so']}%"];
        }
        $time = input('get.end_time');
        if(isset($time) && $time){
            
            $aa = explode(' - ',$time);
            $aa[1] = date("Y-m-d",strtotime("+1 day",strtotime($aa[1])));
            $data = [$aa[0],$aa[1]];
            $where[] =['o.add_time','between',$data];
            /*
            // 将第二个日期（结束日期）加一天，然后计算10天前的日期
            $aa = explode(' - ',$time);
            $end_date = date("Y-m-d", strtotime("+1 day", strtotime($aa[1])));
            $start_date_for_10_days = date("Y-m-d", strtotime("-10 day", strtotime($end_date)));
            // 计算日期差（以天为单位）
            $timestamp_diff = (strtotime($aa[1]) - strtotime($aa[0])) / (60 * 60 * 24); // 转换为秒，然后除以一天中的秒数
            // 准备数据数组
            $data = [$aa[0], $aa[1]];
            // 如果日期差大于10天，则使用10天前的日期作为开始日期
            if ($timestamp_diff > 10) {
                $data = [$start_date_for_10_days, $aa[1]];
            }
            $where[] =['o.add_time','between',$data];
            */
        }else{
            $today = date('Y-m-d');  
            $thirtyDaysAgo = date('Y-m-d', strtotime('-16 days'));  
            $where[] = ['o.add_time','gt',$thirtyDaysAgo];
        }
        $mall_list  = Db::name('mall_product_supper')->order('id asc')->column('supper_name','id');
        $type = Db::name('mall_product')->where([['status','=',1], ['stock','>',0],['is_del','=',0]])->column('title','id');
        $this->assign('goods',$type);
        $this->assign([
            'mall_list'=>$mall_list,
            'authorize'=>session('user.authorize')
        ]);
        #记录总数
        $query = $this->_query("mall_order o")->field("o.*,m.tel as tels,s.supper_name,supper_name_all,spec.market_price")->leftJoin('mall_product p','p.id = o.goods_id')->leftJoin(' member m' , 'm.id=o.member_id')->leftJoin('mall_product_spec spec','spec.spec_id=o.spec_id')->leftJoin('mall_product_supper s' , 's.id=p.supplier');
        $query->order('o.order_id desc')->where($where)->group('o.order_id')->page();
    }

    /**
     * @Auther L
     * 订单发货
     */
    public function on_order() {
        $this->applyCsrfToken();
        $this->_form('mall_order', 'on_order', 'order_id');
    }

    /**
     * 编辑收货地址
     * @return void|null
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function edit_address() {
        if(request()->isGet()){
            $id = $_GET['order_id'];
            $order = Db::name('mall_order')->field('order_id,name,tel,address,address_sub')->where(['order_id'=>$id])->find();
            $order['address'] = explode('-',$order['address']);
            $order['address'][1] = mb_substr($order['address'][1],0,2);
            $this->assign('order',$order);
            return $this->fetch();
        }else{
            $data['name'] = $_POST['name'];
            $data['tel'] = $_POST['tel'];
            $data['address'] = $_POST['provid'].'-'.$_POST['cityid'].'-'.$_POST['areaid'];
            $data['address_sub'] = $_POST['address_sub'];
            $id = $_POST['order_id'];
            $res = Db::name('mall_order')->where(['order_id'=>$id])->update($data);
            Db::name('mall_order')->where(['order_id'=>$id])->inc('address_edit_times')->update();
            if($res){
                $this->success('修改成功');
            }
            $this->error('修改失败');
        }
    }


    /**
     * @Auther L
     * 编辑发货信息
     */
    public function update_order() {
        $this->applyCsrfToken();
        $this->_form('mall_order', 'update_order', 'order_id');
    }
    /**
     * @Auther L
     * 订单详情
     */
    public function order_info()
    {
        $order_id = input('order_id');
        $orderInfo = Db::name('mall_order o')->field('o.*,p.title,p.supper_price,o.num,p.imglogo,ps.name spec_name,ps.market_price,ps.price')->leftJoin('mall_product p',' o.goods_id=p.id')->leftJoin('mall_product_spec ps','ps.spec_id=o.spec_id')->where(['o.order_id' => $order_id])->find();
        $order_status = config('ORDER_STATUS');
        $orderInfo['status'] = $orderInfo['order_status'];
        $orderInfo['order_status'] = $order_status[$orderInfo['order_status']]['name'];
        $orderInfo['is_off'] = $orderInfo['is_off'] ? '关闭交易' : '交易正常';
        $orderInfo['address'] = trim($orderInfo['address']) ? ($orderInfo['address'] .'-'.$orderInfo['address_sub']. ' ' . $orderInfo['name'] . ' ' . $orderInfo['tel']) : '无';
        $orderInfo['tran'] = '暂无';
       $this->assign('order_info',$orderInfo);
       $this->fetch();
    }

    
    
    public function out_goods_excel()
    {
        # 搜索条件
        $where = [];
        if (!empty($_GET['tell'])) {
            $where[] =['m.tel','eq',$_GET['tell']] ;
        }
        if (!empty($_GET['tel'])) {
            $where[] =['o.tel','eq',$_GET['tel']] ;
        }
        /*
        if (!empty($_GET['order_status'])) {
            $where[] =['o.order_status','eq',$_GET['order_status']] ;
        }
        */
        if (!empty($_GET['order_status'])) {
            
            if ($_GET['order_status'] == 9) {
            $where[] = ['o.order_status', 'in', [1,3]];
            }
            else if($_GET['order_status'] == 10){
            $where[] = ['o.order_status', 'in', [1,2,3]];
            }
            else{
            $where[] =['o.order_status','eq',$_GET['order_status']] ;
            }
            
        }
        if (!empty($_GET['is_off'])) {
            $where[] =['o.is_off','eq',$_GET['is_off']] ;
        }
        if (!empty($_GET['pay_type'])) {
            $where[] =['o.pay_type','eq',$_GET['pay_type']] ;
        }
        if(session('user.authorize') == 8){
            $store_id = Db::name('mall_product_supper')->where(['supper_name'=>session('user.username')])->value('id');
            $where[] =['p.supplier','eq',$store_id] ;
        }else{
            if (!empty($_GET['store_id'])) {
                $where[] =['p.supplier','eq',$_GET['store_id']] ;
            }
        }
        if (!empty($_GET['goods_type'])) {
            $where[] =['o.goods_type','eq',$_GET['goods_type']] ;
        }
        if (!empty($_GET['goods_id'])) {
            $where[] =['o.goods_id','eq',$_GET['goods_id']] ;
        }
        if (!empty($_GET['so'])) {
            $where[]= ['o.order_id|o.order_sn|o.address', 'like', "%{$_GET['so']}%"];
        }
        $time = input('get.end_time');
        if(isset($time) && $time){
            /*
            $aa = explode(' - ',$time);
            $aa[1] = date("Y-m-d",strtotime("+1 day",strtotime($aa[1])));
            $data = [$aa[0],$aa[1]];
            $where[] =['o.add_time','between',$data];
            */
            // 将第二个日期（结束日期）加一天，然后计算10天前的日期
            $aa = explode(' - ',$time);
            $aa[1] = date("Y-m-d",strtotime("+1 day",strtotime($aa[1])));
            $end_date = date("Y-m-d", strtotime("+1 day", strtotime($aa[1])));
            $start_date_for_10_days = date("Y-m-d", strtotime("-16 day", strtotime($end_date)));
            // 计算日期差（以天为单位）
            $timestamp_diff = (strtotime($aa[1]) - strtotime($aa[0])) / (60 * 60 * 24); // 转换为秒，然后除以一天中的秒数
            // 准备数据数组
            $data = [$aa[0], $aa[1]];
            // 如果日期差大于10天，则使用10天前的日期作为开始日期
            if ($timestamp_diff > 15) {
                $data = [$start_date_for_10_days, $aa[1]];
            }
             
            // 构造查询条件数组
            $where[] = ['o.add_time', 'between', $data];
        }else{
            $today = date('Y-m-d');  
            $thirtyDaysAgo = date('Y-m-d', strtotime('-16 days'));  
            $where[] = ['o.add_time','gt',$thirtyDaysAgo];
        }
        $orderlist = Db::name('mall_order o')->field('o.*,p.title,spec.name spec_name,s.supper_name,supper_name_all,p.supper_price,spec.market_price')->leftJoin('mall_product p','p.id=o.goods_id')->leftJoin('mall_product_spec spec','spec.spec_id=o.spec_id')->leftJoin('mall_product_supper s','s.id = p.supplier')->where($where)->order('o.order_id desc')->select();
        $GLOBALS['order_status'] = array_column(config('ORDER_STATUS'), 'name', 'key');
        $goods_type = config('goods_type');
        foreach ($orderlist as &$value){
            $value['address'] = $value['address'].' '.$value['address_sub'];
            $value['goods_type'] = $goods_type[$value['goods_type']].'订单';
        }
            $key = [  
            'order_sn' => '订单编号',
            'order_amount|"###\t"' => '订单金额',
            'order_bal_amount|"###\t"' => '优惠券抵扣金额',
            'amount|"###\t"' => '实付积分',
            'pay_mchid|"###\t"' => '供货价',
            'goods_type|"###\t"' => '订单类型',
            'order_status|$GLOBALS["order_status"][###]' => '订单状态',
            'member_id|"###\t"' => '购买会员ID',
            'add_time|"###\t"' => '下单时间',
            'send_time|"###\t"' => '发货时间',
            'address' => '收货地址',
            'tel|"###\t"' => '收货人手机',
            'name' => '收货人姓名',
            'tran_type' => '发货快递',
            'tran_sn|"###\t"' => '发货快递单号',
            'title' => '购买商品',
            'supper_name' => '供应商简称',
            'supper_name_all' => '供应商全称',
            'num|"###\t"' => '购买数量',
            'spec_name' => '购买规格'
            ];  
  
            // 使用Csv类生成CSV字符串  
            $csvString = Csv::main()->out($orderlist, $key);  
          
          
            $date = date('YmdHis'); // 例如，202304101619  
            $zipFileNamex = "csv{$date}"; // 构建ZIP文件名
            // 创建ZIP文件内容  
            $zip = new ZipArchive();  
            $zipFileName = tempnam(sys_get_temp_dir(), $zipFileNamex);  
            $zip->open($zipFileName, ZipArchive::CREATE | ZipArchive::OVERWRITE);  
            $zip->addFromString('orders.csv', mb_convert_encoding($csvString, 'gbk', 'utf-8'));  
            $zip->close();  
          
            // 清理输出缓冲区  
            if (ob_get_level()) {  
                ob_end_clean();  
            }  
          
            // 提供ZIP文件下载  
            header('Content-Type: application/zip');  
            header('Content-Disposition: attachment; filename="' . basename($zipFileName) . '"'.'.zip');  
            header('Content-Length: ' . filesize($zipFileName));  
            readfile($zipFileName);  
          
            // 删除临时ZIP文件  
            unlink($zipFileName);  
    }
    
    /**
     * 批量发货
     * @return void
     * */
    public function batch_goods()
    {
        $excel = $_FILES['data'];
        $key = md5_file($excel['tmp_name']);
        $fp = fopen($excel['tmp_name'], 'r');
        $extension = strtolower(pathinfo($excel['name'], PATHINFO_EXTENSION));
        if ($extension != 'csv') {
            return [
                'status'=>false,
                'code'=>'上传模板文件类型错误!'
            ];
        }
        $order_status = config('kuaidi');
        $result = [];
        while (!feof($fp)) {
            $data = gbkToUtf8(fgetcsv($fp));
            if (empty($data)) {
                continue;
            }
            
            $courier_name = $data[1] ?? ''; // 使用 null 合并运算符确保 $data[1] 存在
            //if (!array_key_exists($courier_name, $order_status)) {
            if (!array_key_exists($courier_name, $order_status) || $courier_name === '自提') {
                array_push($data, '失败: 错误的物流公司');
                $result[] = $data;
                continue;
            }
            $pattern = '/^[A-Za-z0-9]+$/';
            if (!$data[2] || !preg_match($pattern, $data[2])) {
                array_push($data, '失败: 未填入或快递单号格式错误');
                $result[] = $data;
                continue;
            }
            $order_sn = Db::name('mall_order')->where(['order_sn'=>$data[0]])->value('order_id');
            if(!$order_sn){
                array_push($data, '失败: 无效的订单号');
                $result[] = $data;
                continue;
            }
            $res = Db::name('mall_order')->where(['order_sn' => $data[0]/*,'order_status'=>1*/])->update([
                'send_time' => date('Y-m-d H:i:s'),
                'tran_type' => $data[1],
                'tran_sn' => $data[2],
                'tran_note' => $data[3] ?: '',
                'order_status'=>Db::raw('if(order_status=1,2,order_status)')
            ]);
            if ($res) {
                array_push($data, '成功: 导入成功');
            } else {
                array_push($data, '失败: 订单不存在,或其他原因');
            }
            $result[] = $data;
//            print_r($data);
        }

        $lest['batch_goods'][$key]['data'] = $result;
        $lest['batch_goods'][$key]['name'] = $excel['name'];
        cache('list',$lest,60);

        return [
            'status'=>true,
            'code'=>'导入成功!即将下载发货结果',
            'data'=>$key
        ];
    }

    /**
     * 下载批量导入结果
     * @action
     * */
    /*
    public function batch_down()
    {
        $key = input('key');
        $list = cache('list');
        $file = $list['batch_goods'][$key];
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . pathinfo($file['name'], PATHINFO_FILENAME) . '_result.csv"');
        echo Csv::main()->out($file['data']);
    }
    */
    public function batch_down(){
    $key = input('key');
    $list = cache('list');
    $file = $list['batch_goods'][$key];

    // 生成CSV内容
    $csvData = Csv::main()->out($file['data']);
    
    // 将CSV内容转换为GB2312编码
    $csvData = mb_convert_encoding($csvData, 'GB2312', 'UTF-8');

    // 设置下载头
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . pathinfo($file['name'], PATHINFO_FILENAME) . '_result.csv"');

    // 输出CSV数据
    echo $csvData;
    }


    public function deliver_goods(){
        $this->title='批量发货';
        $this->fetch();
    }

}