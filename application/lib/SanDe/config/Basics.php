<?php
return [
    // 公钥文件
    'publicKeyPath'  =>  '../application/lib/SanDe/cert/sand.cer',
    // 私钥文件
    'privateKeyPath' =>  '../application/lib/SanDe/cert/MID_RSA_PRIVATE_KEY_100211701160001_new.pfx',
    // 私钥证书密码
    'privateKeyPwd'  =>  'sand2019',
        // 接口地址
    'apiUrl'         =>  'https://cashier1.sandpay.com.cn',

    'variable' =>[
        // 商户号
        'mid'      =>  '100211701160001',
        // 产品id https://open.sandpay.com.cn/product/detail/43984//
        'productId'      =>  '00002000',
        // 接入类型  1-普通商户接入 2-平台商户接入 3-核心企业商户接入
        'accessType'     =>  '1',
        // 渠道类型  07-互联网  08-移动端
        'channelType'     =>  '07',
        // 平台ID accessType为2时必填，在担保支付模式下填写核心商户号
        'plMid'          =>  '',
    ],
]; 


