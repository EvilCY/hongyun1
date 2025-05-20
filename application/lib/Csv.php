<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 16-3-17
 * Time: 下午9:38
 */
namespace app\lib;
class Csv {
    /**
     * 入口方法
     * @return object
     * */
    public static function main(){
        return new self();
    }

    /**
     * 导出表格数据
     * @param
     * @param
     * @return string|mixed
     * */
    public function out($valArr,$key=null){
        $reStrArr = array();
        foreach($valArr as $val){

            $argv = array();
            if($key===null){
                foreach($val as $v){
                    $argv[] = $v;
                }
            }else{
                foreach($key as $k=>$v){
                    $keys = explode('|',$k);
                    if(@$keys[3])echo "return ".str_replace('###',addslashes($val[@$keys[0]]),@$keys[1]).";";
                    $argv[] = @$keys[1] == null?$val[@$keys[0]]:eval("return ".str_replace('###',addslashes($val[@$keys[0]]),@$keys[1]).";");
                }
            }
            $reStrArr[] = self::csvstr_join($argv);
        }
        $reStr = join("\r\n",$reStrArr);

        return $key === null?self::csvstr_join(array_keys($valArr[0]))."\r\n".$reStr:self::csvstr_join($key)."\r\n".$reStr;

    }

    /**
     * 导入CSV表格数据
     * @param
     * @param
     * @return array|mixed
     *
     * */
    public function put(){


    }
    /**
     * 合并csv数据
     * @param array $arr
     * @param string $punctuation
     * @return string|mixed
     * */
    private static function csvstr_join($arr,$punctuation='"'){
        $reArr = array();
        foreach($arr as $v){
            $reArr[] = $punctuation.$v.$punctuation;
        }
        return join(',',$reArr);
    }

} 