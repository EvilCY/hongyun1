<?php
date_default_timezone_set('Asia/Shanghai');
/**
 * 杉德所有参数说明
 * https://open.sandpay.com.cn/product/detail/43301/43332/43333
 */
return [
    'head' => [
        'version'=>[
            'name'      => '版本号',
            'param'     => 'version',
            'type'      => 'String',
            'lengthmax' => '3',
            'require'   => 'true',
            'describe'  => '默认1.0'
        ],
        'method'=>[
            'name'      => '接口名称',
            'param'     => 'method',
            'type'      => 'String',
            'lengthmax' => '128',
            'require'   => 'true',
            'describe'  => '参考各接口文档中"请求地址-method"'
        ],
        'productId'=>[
            'name'      => '产品编码',
            'param'     => 'productId',
            'type'      => 'String',
            'lengthmax' => '8',
            'require'   => 'true',
            'describe'  => '点击参考各产品编码'
        ],
        'accessType'=>[
            'name'      => '接入类型',
            'param'     => 'accessType',
            'type'      => 'String',
            'lengthmax' => '1',
            'require'   => 'true',
            'describe'  => '1-普通商户接入、2-平台商户接入、3-核心企业商户接入'
        ],
        'mid'=>[
            'name'      => '商户ID',
            'param'     => 'mid',
            'type'      => 'String',
            'lengthmax' => '15',
            'require'   => 'true',
            'describe'  => '收款方商户号'
        ],
        'plMid'=>[
            'name'      => '平台ID',
            'param'     => 'plMid',
            'type'      => 'String',
            'lengthmax' => '15',
            'require'   => false,
            'describe'  => '接入类型为2时必填，在担保支付模式下填写核心商户号'
        ],
        'channelType'=>[
            'name'      => '渠道类型',
            'param'     => 'channelType',
            'type'      => 'String',
            'lengthmax' => '2',
            'require'   => 'true',
            'describe'  => '商户的真实应用场景，可选项包括： 07-互联网 08-移动端'
        ],
        'reqTime'=>[
            'name'      => '请求时间',
            'param'     => 'reqTime',
            'type'      => 'String',
            'lengthmax' => '14',
            'require'   => 'true',
            'describe'  => '格式：yyyyMMddHHmmss'
        ]
    ],
    'body' => [
        'orderCreate' => [
            'orderCode' =>  [
                'name'      => '商户订单号',
                'param'     => 'orderCode',
                'type'      => 'String',
                'lengthmax' => '30',
                'require'   => 'true',
                'describe'  => '长度12位起步，商户唯一，建议订单号有日期',
                'value'     => date('YmdHis', time()) + '0601'
            ],
            'totalAmount' =>  [
                'name'      => '订单金额',
                'param'     => 'totalAmount',
                'type'      => 'String',
                'lengthmax' => '12',
                'require'   => 'true',
                'describe'  => '例 000000000101 代表 1.01 元',
                'value'     => '000000000012'
            ],
            'subject' =>  [
                'name'      => '订单标题',
                'param'     => 'subject',
                'type'      => 'String',
                'lengthmax' => '40',
                'require'   => 'true',
                'describe'  => '',
                'value'     => '话费充值'
            ],
            'body' =>  [
                'name'      => '订单描述',
                'param'     => 'body',
                'type'      => 'String',
                'lengthmax' => '256',
                'require'   => 'true',
                'describe'  => '订单信息详细描述，JSON格式：{ mallOrderCode:商城订单号 receiveAddress：收货地址 goodsDesc：商品描述}',
                'value'     => '用户购买话费0.12'
            ],
            'notifyUrl' =>  [
                'name'      => '异步通知地址',
                'param'     => 'notifyUrl',
                'type'      => 'String',
                'lengthmax' => '256',
                'require'   => 'true',
                'describe'  => '杉德支付主动通知商户订单支付结果的https路径。通知地址必须为直接可以访问的URL。该地址需向杉德报备。异步通知地址报备方法',
                'value'     => 'http://ylui.vegclubs.com/'
            ],
            'frontUrl' =>  [
                'name'      => '前台通知地址',
                'param'     => 'frontUrl',
                'type'      => 'String',
                'lengthmax' => '256',
                'require'   => 'true',
                'describe'  => '支付结束后跳转回商户平台的http/https路径',
                'value'     => 'http://192.168.62.61/sandpay-qr-phpdemo/notifyurl.php'
            ],

            // 'userId' =>  [
            //     'name'      => '付款方Id',
            //     'param'     => 'userId',
            //     'type'      => 'String',
            //     'lengthmax' => '30',
            //     'require'   => 'false',
            //     'describe'  => '',
            //     'value'     => ''
            // ],

            'extend' =>  [
                'name'      => '扩展域',
                'param'     => 'extend',
                'type'      => 'String',
                'lengthmax' => '256',
                'require'   => 'false',
                'describe'  => '',
                'value'     => ''
            ],

            'accsplitInfo' =>  [
                'name'      => '分账域',
                'param'     => 'accsplitInfo',
                'type'      => 'String',
                'lengthmax' => '256',
                'require'   => 'false',
                'describe'  => '',
                'value'     => ''
            ],
            'clearCycle' =>  [
                'name'      => '清算模式',
                'param'     => 'clearCycle',
                'type'      => 'String',
                'lengthmax' => '1',
                'require'   => 'false',
                'describe'  => '',
                'value'     => ''
            ],

            'txnTimeOut' =>  [
                'name'      => '订单超时时间',
                'param'     => 'txnTimeOut',
                'type'      => 'String',
                'lengthmax' => '14',
                'require'   => 'false',
                'describe'  => '',
                'value'     => ''
            ],
        ],
        'orderRefund' => [
            'orderCode' =>  [
                'name'      => '商户订单号',
                'param'     => 'orderCode',
                'type'      => 'String',
                'lengthmax' => '32',
                'require'   => 'true',
                'describe'  => '指发起交易的流水号，建议订单号有日期',
                'value'     => date('YmdHis', time()) + '0601'
            ],
            'oriOrderCode' =>  [
                'name'      => '原商户订单号',
                'param'     => 'oriOrderCode',
                'type'      => 'String',
                'lengthmax' => '32',
                'require'   => 'true',
                'describe'  => '待退款的商户订单号',
                'value'     => '2017091551421977'
            ],
            'refundAmount' =>  [
                'name'      => '退款金额',
                'param'     => 'refundAmount',
                'type'      => 'String',
                'lengthmax' => '12',
                'require'   => 'true',
                'describe'  => '例 000000000101 代表 1.01 元',
                'value'     => '000000000101'
            ],
            'notifyUrl' =>  [
                'name'      => '异步通知地址',
                'param'     => 'notifyUrl',
                'type'      => 'String',
                'lengthmax' => '256',
                'require'   => 'true',
                'describe'  => '杉德支付主动通知商户订单支付结果的https路径。通知地址必须为直接可以访问的URL。 该地址需向杉德报备。异步通知地址报备方法(https://open.sandpay.com.cn/accessGuide/)',
                'value'     => 'http://ylui.vegclubs.com/'
            ],
            'refundReason' =>  [
                'name'      => '退款原因',
                'param'     => 'refundReason',
                'type'      => 'String',
                'lengthmax' => '256',
                'require'   => false,
                'describe'  => '描述退款申请原因',
                'value'     => 'test测试'
            ],
            'refundMarketAmount' =>  [
                'name'      => '退营销金额',
                'param'     => 'refundMarketAmount',
                'type'      => 'String',
                'lengthmax' => '12',
                'require'   => false,
                'describe'  => '描述退款申请原因',
                'value'     => ''
            ],
            'extend' =>  [
                'name'      => '扩展域',
                'param'     => 'extend',
                'type'      => 'String',
                'lengthmax' => '256',
                'require'   => false,
                'describe'  => '如上送，在异步通知和查询接口中将返回相同的值',
                'value'     => ''
            ]
        ],
        'orderQuery' => [
            'orderCode' =>  [
                'name'      => '商户订单号',
                'param'     => 'orderCode',
                'type'      => 'String',
                'lengthmax' => '32',
                'require'   => 'true',
                'describe'  => '支持商户订单号orderCode、通道订单号bankserial、渠道订单号payordercode查询，建议使用商户订单号ordercode，渠道订单号payordercode查询',
                'value'     => date('YmdHis', time()) + '0601'
            ],
            'extend' =>  [
                'name'      => '扩展域',
                'param'     => 'extend',
                'type'      => 'String',
                'lengthmax' => '256',
                'require'   => false,
                'describe'  => '',
                'value'     => ''
            ]
        ],
        'clearfileDownload' => [
            'clearDate' =>  [
                'name'      => '交易日期',
                'param'     => 'clearDate',
                'type'      => 'String',
                'lengthmax' => '8',
                'require'   => 'true',
                'describe'  => '指发起交易的流水号，建议订单号有日期',
                'value'     => date('YmdHis', time()) + '0601'
            ],
            'fileType' =>  [
                'name'      => '文件返回类型',
                'param'     => 'fileType',
                'type'      => 'String',
                'lengthmax' => '1',
                'require'   => 'true',
                'describe'  => '默认1-订单明细文件(clearDate为交易日期)；2-账单流水文件(clearDate为结算日期)；3-供应链订单明细文件 (clearDate为交易日期)4-账户变动明细文件 (clearDate为结算日期)。（3或4仅支持担保支付模式的商户）',
                'value'     => '1'
            ],
            'extend' =>  [
                'name'      => '扩展域',
                'param'     => 'extend',
                'type'      => 'JSON',
                'lengthmax' => '256',
                'require'   => false,
                'describe'  => '',
                'value'     => ''
            ]
        ],
        'orderMcAutoNotice' => [
            'orderCode' =>  [
                'name'      => '商户订单号',
                'param'     => 'orderCode',
                'type'      => 'String',
                'lengthmax' => '64',
                'require'   => 'true',
                'describe'  => '',
                'value'     => date('YmdHis', time()) + '0601'
            ],
            'noticeType' =>  [
                'name'      => '通知类型',
                'param'     => 'noticeType',
                'type'      => 'String',
                'lengthmax' => '2',
                'require'   => 'true',
                'describe'  => '00-交易通知、01-退货通知、02-分账通知、03-分账撤销通知、04-分账完结通知',
                'value'     => '00'
            ]
            
        ],
        'orderFileDownload' => [
            'orderCode' =>  [
                'name'      => '商户订单号',
                'param'     => 'orderCode',
                'type'      => 'String',
                'lengthmax' => '50',
                'require'   => 'true',
                'describe'  => '长度12位起步，商户订单号唯一，建议订单号有日期',
                'value'     => ''
            ],
            'orderDate' =>  [
                'name'      => '订单日期',
                'param'     => 'orderDate',
                'type'      => 'String',
                'lengthmax' => '8',
                'require'   => 'true',
                'describe'  => 'YYYYMMDD',
                'value'     => ''
            ]

        ],
        'BackGoodsNotice' => [
            'url' =>  [
                'name'      => '请求地址',
                'param'     => 'url',
                'type'      => 'String',
                'lengthmax' => null,
                'require'   => 'true',
                'describe'  => '根据商户上送异步通知地址',
                'value'     => 'http://ylui.vegclubs.com/'
            ]

        ],
        
        
    ]
]; 


