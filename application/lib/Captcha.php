<?php
/**
 * Created by PhpStorm.
 * User: Angerl
 * Date: 2020/3/12
 * Time: 22:57
 */

namespace app\lib;

session_start();
class Captcha
{
    public function create($flag=null){

        $image=imagecreatetruecolor(100, 30);
//背景颜色为白色
        $color=imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 20, 20, $color);

        $code='';
        for($i=0;$i<4;$i++){
            $fontSize=10;
            $x=rand(5,10)+$i*100/4;
            $y=rand(5, 15);
            // $data='abcdefghijklmnpqrstuvwxyz123456789';
            $data='0123456789';
            $index = mt_rand(0, strlen($data)-1);
            $string=substr($data,$index,1);
            $code.=$string;
            $color=imagecolorallocate($image,rand(0,120), rand(0,120), rand(0,120));
            imagestring($image, $fontSize, $x, $y, $string, $color);
        }
        session('captcha_'.$flag,$code);//存储在session里
        for($i=0;$i<200;$i++){
            $pointColor=imagecolorallocate($image, rand(100, 255), rand(100, 255), rand(100, 255));
            imagesetpixel($image, rand(0, 100), rand(0, 30), $pointColor);
        }

        for($i=0;$i<2;$i++){
            $linePoint=imagecolorallocate($image, rand(150, 255), rand(150, 255), rand(150, 255));
            imageline($image, rand(10, 50), rand(10, 20), rand(80,90), rand(15, 25), $linePoint);
        }
        ob_clean();
        header ('Content-Type: image/png');
        imagepng($image);
        imagedestroy($image);
        return ob_get_clean();
    }
    public function verify($code,$flag=null){
        return true;
        if(!$code) return false;
        $auth_code = session('captcha_'.$flag);
        if(!$auth_code) return false;
        if($code==$auth_code){
            session('captcha_'.$flag,null);
            return true;
        }else{
            return false;
        }
    }
}