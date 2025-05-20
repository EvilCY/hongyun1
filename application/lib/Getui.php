<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2020/7/21
 * Time: 11:26
 */

namespace app\lib;
header("Content-Type: text/html; charset=utf-8");

require_once(dirname(__FILE__) . '/getui/' . 'IGt.Push.php');
require_once(dirname(__FILE__) . '/getui/' . 'igetui/IGt.AppMessage.php');
require_once(dirname(__FILE__) . '/getui/' . 'igetui/IGt.APNPayload.php');
require_once(dirname(__FILE__) . '/getui/' . 'igetui/template/IGt.BaseTemplate.php');
require_once(dirname(__FILE__) . '/getui/' . 'IGt.Batch.php');
require_once(dirname(__FILE__) . '/getui/' . 'igetui/utils/AppConditions.php');
require_once(dirname(__FILE__).'/getui/'.'igetui/template/notify/IGt.Notify.php');

class Getui{
    const HOST = 'http://api.getui.com/apiex.htm';
//    const APPID = 'EFqwPqBbsq7GoulPQ6I54';
//    const APPKEY = 'EArEwNErDS78Kv2FMVwjy8';
//    const MASTERSECRET = 'F6ZICH9N1z6vsNIriss207';
    const APPID = 'U4RVRHChGN7zPHJO7EX3n9';
    const APPKEY = 'tOvWuF0stDAyxnFLBxnGm3';
    const MASTERSECRET = 'xhdRxR67rf78Ni5tlo0uP7';
    public function pushMessagetoSingle($client_id,$title,$content,$path,$notifyId=false){
        // STEP1：选择模板
        $template =  new \IGtNotificationTemplate();
        // 设置APPID与APPKEY
        $template->set_appId(self::APPID);//应用appid
        $template->set_appkey(self::APPKEY);//应用appkey
        //设置模板参数
        $template->set_title($title);//通知栏标题
        $template->set_text($content);//通知栏内容
        $template->set_isRing(true);//是否响铃
        $template->set_isVibrate(true);//是否震动
        $template->set_isClearable(true);//通知栏是否可清除
        $template->set_transmissionType(1);
        $template->set_transmissionContent($path);
        if($notifyId){
            $template->set_notifyId($notifyId);
        }
        // STEP2：设置推送其他参数
        $message = new \IGtSingleMessage();
        $message->set_isOffline(true);
        $message->set_offlineExpireTime(60 * 60 * 1000);
        $message->set_data($template);

        $target = new \IGtTarget();
        $target->set_appId(self::APPID);
        $target->set_clientId($client_id);

        // STEP3：执行推送
        $igt = new \IGeTui(self::HOST,self::APPKEY,self::MASTERSECRET);
        $ret = $igt->pushMessageToSingle($message, $target);
        return $ret['result'] == 'ok';
    }
//    public function pushMessagetoSingle($client_id){
//        $package = 'uni.UNI63344FD';
//        $title = 'U比生活标题';
//        $content = 'U比生活推送内容';
//        $payload = '{"id":"1234567890"}';
//        // 生成指定格式的intent支持厂商推送通道
//        $intent = "intent:#Intent;action=android.intent.action.oppopush;launchFlags=0x14000000;component={$package}/io.dcloud.PandoraEntry;S.UP-OL-SU=true;S.title={$title};S.content={$content};S.payload={$payload};end";
//
//        $template = $this->createPushMessage($payload,$intent,$title,$content);
//        $message = new \IGtSingleMessage();
//        $message->set_isOffline(true);
//        $message->set_offlineExpireTime(60 * 60 * 1000);
//        $message->set_data($template);
//
//        $igt = new \IGeTui(self::HOST,self::APPKEY,self::MASTERSECRET);
//        $target = new \IGtTarget();
//        $target->set_appId(self::APPID);
//        $target->set_clientId($client_id);
//        $ret = $igt->pushMessageToSingle($message, $target);
//        var_dump($ret);die();
//    }
//    function IGtNotificationTemplateDemo(){
//        $title ='DMD交易中心';
//        $content ='您有新的订单状态变化，请及时处理！';
//        $payload = json_encode(['path'=>'/pages/dmd/trade/trade']);
//        $package = 'uni.UNI63344FD';
//        $intent = "intent:#Intent;action=android.intent.action.oppopush;launchFlags=0x14000000;component={$package}/io.dcloud.PandoraEntry;S.UP-OL-SU=true;S.title={$title};S.content={$content};S.payload={$payload};end";
//
//        $template =  new \IGtTransmissionTemplate();
//        $template->set_appId(self::APPID);                   //应用appid
//        $template->set_appkey(self::APPKEY);                 //应用appkey
//        $template->set_transmissionType(2);            //透传消息类型
//        //为了保证应用切换到后台时接收到个推在线推送消息，转换为{title:'',content:'',payload:''}格式数据，UniPush将在系统通知栏显示
//        //如果开发者不希望由UniPush处理，则不需要转换为上述格式数据（将触发receive事件，由应用业务逻辑处理）
//        //注意：iOS在线时转换为此格式也触发receive事件
//        $template->set_transmissionContent($payload);//透传内容
//        //STEP4：设置响铃、震动等推送效果
////        $template->set_isRing(true);                   //是否响铃
////        $template->set_isVibrate(true);                //是否震动
////        $template->set_isClearable(true);              //通知栏是否可清除
//        //兼容使用厂商通道传输
//        $notify = new \IGtNotify();
//        $notify->set_title($title);
//        $notify->set_content($content);
//        $notify->set_intent($intent);
//        $notify->set_type(\NotifyInfo_type::_intent);
//        $template->set3rdNotifyInfo($notify);
//        return $template;
//    }
//    // 创建支持厂商通道的透传消息
//    function createPushMessage($p, $i, $t, $c){
//        $template =  new \IGtTransmissionTemplate();
//        $template->set_appId(self::APPID);//应用appid
//        $template->set_appkey(self::APPKEY);//应用appkey
//        $template->set_transmissionType(2);//透传消息类型:1为激活客户端启动
//
//        //为了保证应用切换到后台时接收到个推在线推送消息，转换为{title:'',content:'',payload:''}格式数据，UniPush将在系统通知栏显示
//        //如果开发者不希望由UniPush处理，则不需要转换为上述格式数据（将触发receive事件，由应用业务逻辑处理）
//        //注意：iOS在线时转换为此格式也触发receive事件
//        $payload = array('title'=>$t, 'content'=>$c);
//        $pj = json_decode($p, TRUE);
//        $payload['payload'] = is_array($pj)?$pj:$p;
//        $template->set_transmissionContent(json_encode($payload));//透传内容
//
//        //兼容使用厂商通道传输
//        $notify = new \IGtNotify();
//        $notify->set_title($t);
//        $notify->set_content($c);
//        $notify->set_intent($i);
//        $notify->set_type(\NotifyInfo_type::_intent);
//        $template->set3rdNotifyInfo($notify);
//        //个推老版本接口: $template ->set_pushInfo($actionLocKey,$badge,$message,$sound,$payload,$locKey,$locArgs,$launchImage);
//        //$template->set_pushInfo('', 0, $c, '', $p, '', '', '');
//
//        return $template;
//    }
}