<?php
date_default_timezone_set('Asia/Shanghai');
header('Content-type:text/html;charset=utf-8');
// header('content-type:application/json;charset=utf-8');
require '../api/Common.php';
require '../api/PCCashier.php';

$data=$_POST;
file_put_contents('send.log', json_encode($data));

// $data = <<<'tag'
// {"charset":"UTF-8","data":"{\"head\":{\"version\":\"1.0\",\"respTime\":\"20210421142033\",\"respCode\":\"000000\",\"respMsg\":\"成功\"},\"body\":{\"mid\":\"68888xxx1\",\"orderCode\":\"cz20210421141834974855\",\"tradeNo\":\"cz20210421141834974855\",\"clearDate\":\"20210421\",\"totalAmount\":\"000000000001\",\"orderStatus\":\"1\",\"payTime\":\"20210421142033\",\"settleAmount\":\"000000000001\",\"buyerPayAmount\":\"000000000001\",\"discAmount\":\"000000000000\",\"txnCompleteTime\":\"20210421142032\",\"payOrderCode\":\"20210421001378700000000000067481\",\"accLogonNo\":\"152****2691\",\"accNo\":\"\",\"midFee\":\"000000000000\",\"extraFee\":\"000000000000\",\"specialFee\":\"000000000000\",\"plMidFee\":\"000000000000\",\"bankserial\":\"022021042122001438921456090048\",\"externalProductCode\":\"00000006\",\"cardNo\":\"\",\"creditFlag\":\"\",\"bid\":\"\",\"benefitAmount\":\"000000000000\",\"remittanceCode\":\"\",\"extend\":\"\"}}","signType":"01","sign":"CZruKZAmRYRwYdh0VgnojeVH8a2yQ5GJtzNx+LYFh/1MkMyAnRTzHCekHrD+NXgSKm93JolQr24ZPdsElAG6VyPbpKEcymbzaWy2v3Ztbd6Gu1KVRFyJXjTkFuKG9Xneebe5CjXuy6ijDgZxUJSMP4zSuaIWbWSUZA6YkgYpQKr6q8ZJ8TD2XAJsANV470JWPEQcCOKZvYkht19MtmmsrgzQMGzd8RSNdV6QL/ALrLD4JRJ6VyWe2/E/35Puh8UqoMCt9qbnJHkNpJNrrmTzBOoNPl2UIHWghj74IRxKCmwpSczWaQvOwdW7PEz8Rf15fyL2goQb3t7x9M7Dnf18Fw==","extend":"","midBackNoticeUrl":"http://slp.99heng.cn/index.php/pay/Notify/notifyRebackRecharge"}
// tag;
// $data = json_decode($data, true);

verify($data['data'], $data['sign']);

function verify($plainText, $sign)
{
    $resource = openssl_pkey_get_public(publicKey());
    $result   = openssl_verify($plainText, base64_decode($sign), $resource);
    openssl_free_key($resource);
    var_dump('校验结果===========');
    var_dump($result);
}
 function publicKey()
{
    try {
        $file = file_get_contents('./sand.cer');
        if (!$file) {
            throw new \Exception('getPublicKey::file_get_contents ERROR');
        }
        $cert   = chunk_split(base64_encode($file), 64, "\n");
        $cert   = "-----BEGIN CERTIFICATE-----\n" . $cert . "-----END CERTIFICATE-----\n";
        $res    = openssl_pkey_get_public($cert);
        $detail = openssl_pkey_get_details($res);
        openssl_free_key($res);
        if (!$detail) {
            throw new \Exception('getPublicKey::openssl_pkey_get_details ERROR');
        }
        return $detail['key'];
    } catch (\Exception $e) {
        throw $e;
    }
}
