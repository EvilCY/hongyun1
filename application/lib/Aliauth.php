<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2020/4/22
 * Time: 12:53
 */
namespace app\lib;
require __DIR__ . '/alibaba/autoload.php';
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

class Aliauth
{
    private $accessKeyId;
    private $accessKeySecret;
    private $BizType;

    public function __construct($accessKeyId,$accessKeySecret,$BizType){
        $this->accessKeyId = $accessKeyId;
        $this->accessKeySecret = $accessKeySecret;
        $this->BizType = $BizType;
    }

    public function getToken($auth_info,&$res)
    {
        AlibabaCloud::accessKeyClient($this->accessKeyId, $this->accessKeySecret)
            ->regionId('cn-hangzhou')
            ->asDefaultClient();
        try {
            $query = array_merge([
                'RegionId' => "cn-hangzhou",
                'BizType' => $this->BizType,
            ],$auth_info);
            $result = AlibabaCloud::rpc()
                ->product('Cloudauth')
                ->version('2019-03-07')
                ->action('DescribeVerifyToken')
                ->method('POST')
                ->host('cloudauth.aliyuncs.com')
                ->options([
                    'query' => $query,
                ])
                ->request();
            $res = $result->toArray()['VerifyToken'];
            return true;
        } catch (ClientException $e) {
            $res = $e->getErrorMessage();
            return false;
        } catch (ServerException $e) {
            $res = $e->getErrorMessage();
            return false;
        }
    }
    public function verifyResult($BizId,&$res){
        AlibabaCloud::accessKeyClient($this->accessKeyId, $this->accessKeySecret)
            ->regionId('cn-hangzhou')
            ->asDefaultClient();
        try {
            $result = AlibabaCloud::rpc()
                ->product('Cloudauth')
                ->version('2019-03-07')
                ->action('DescribeVerifyResult')
                ->method('POST')
                ->host('cloudauth.aliyuncs.com')
                ->options([
                    'query' => [
                        'RegionId' => "cn-hangzhou",
                        'BizType' => $this->BizType,
                        'BizId' => $BizId
                    ],
                ])
                ->request();
            $req = $result->toArray();
            if($req['VerifyStatus']!=1){
                $res = '未认证';
                return false;
            }else{
                $res = '认证成功';
                return true;
            }
        } catch (ClientException $e) {
            $res = $e->getErrorMessage();
            return false;
        } catch (ServerException $e) {
            $res = $e->getErrorMessage();
            return false;
        }
    }
}