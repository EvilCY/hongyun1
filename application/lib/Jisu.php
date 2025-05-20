<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2020/6/29
 * Time: 11:19
 */

namespace app\lib;


class Jisu
{
    //接口地址
    static private $_link = 'https://api.jisuapi.com';

    //配置信息
    public $config = [
        'api_key' => '78e50be9c5d67edb',
        'secret_key' => 'smAaE71fk3YBI9TZc8DcalzzodDcY2vB'
    ];

    //错误信息
    protected $error = '';

    /**
     * 构造
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取对应接口地址
     * @param string $mod
     * @return string
     */
    protected function getAPILink($mod)
    {
        return self::$_link .'/'.$mod;
    }


    /**
     * 提交充值话费订单
     * @param string $tell 充值手机号
     * @param float $amount 充值金额
     * @param string $order_sn 充值订单号
     * @param string $notify_url 回调地址
     * @return bool|mixed
     */
    public function submitOrder($tell, $amount, $order_sn)
    {
        $url = $this->getAPILink('mobilerecharge/recharge');
        $data = [
            'mobile' => $tell,
            'amount' => $amount,
            'outorderno' => $order_sn
        ];
        $this->sign($data);
        $data['appkey'] = $this->config['api_key'];
        return $this->requestPost($url, $data);
    }


    /**
     * 获取上一次接口错误
     * @return string
     * */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 检测是否错误
     * @param string $res
     * @return bool|mixed
     */
    protected function checkError($res)
    {
        if ($res === false) {//请求失败
            $this->error = Curl::main()->getError();
            return false;
        }
        $data = json_decode($res, TRUE);
        if ($data['status'] != 0) {
            $this->error = $data['msg'];
            return false;
        }
        return $data;
    }

    /**
     * 发送get请求
     * @param string $url
     * @param mixed $data
     * @return bool|mixed
     */
    protected function requestGet($url, $data = [])
    {
        $res = Curl::main()->url($url)->get($data);
        return $this->checkError($res);
    }
    /**
     * 发送post请求
     * @param string $url
     * @param mixed $data
     * @return bool|mixed
     */
    protected function requestPost($url, $data = [])
    {
        $res = Curl::main()->url($url)->post($data);
        return $this->checkError($res);
    }

    /**
     * 数据加签名
     * @param array $data
     * @return string
     */
    public function getSign(array $data)
    {
        ksort($data);
        $paramString = '';
        foreach ($data as $key => $value) {
            $paramString .= $value;
        }
        return md5($paramString . $this->config['secret_key']);
    }

    /**
     * 数据加签
     * @param array $data
     */
    public function sign(array &$data)
    {
        $data['sign'] = $this->getSign($data);
    }

    /**
     * 针对notify_url来验证消息是否是话费多发出的合法消息
     * @return bool
     */
    public function verifyNotify()
    {
        $mobile = isset($_GET['mobile']) ? $_GET['mobile'] : '';
        $sign = isset($_GET['$sign']) ? $_GET['amount'] : '';
        $amount = isset($_GET['amount']) ? $_GET['amount'] : '';
        $outorderno = isset($_GET['order_sn']) ? $_GET['$order_sn'] : '';
        return $sign == md5($amount.$mobile.$outorderno.$this->config['api_key']);
    }
}