<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/7/8
 * Time: 16:07
 * VOD业务功能父类，各平台特有业务在平台子类中实现
 * 子类命名规则：vodSite<siteID>
 * 平台子类文件命名规则：vodSite<siteID>.class.php
 *
 */

//定义site编码的类。!!注意!!虽然目前与推送平台取相同的编码，但不是等同的概念，以后也不保证两者编码一致。
class VODSITE{
    const YWZ=1;
    const AliShangHai=5;
}
abstract class vodBase{
    /**
     * @param int $site     vod平台编码
     * @param string $name  指定子类名称，这时忽略$site参数
     * @return null 或子类实例
     */
    static public function instance($site,$name=''){
        $className=(''==$name)?'vodSite'.$site : $name;
        $fileName=COMMON_PATH.'vod/'.$className.'.class.php';

//var_dump($fileName);
        if(is_file($fileName)){
            require_once($fileName);
            return new $className();
        }
        else {
            return null;
        }
    }

    /**
     * 通过UTC时间字串，获取当地时间戳
     * @param string $utc   UTC时间，格式：yyyy-MM-ddTHH:mm:ssZ
     * @return false|int|string
     */
    public function UTC2LocalTimestemp($utc){
        $time= str_replace(array('T','Z'),' ',$utc);
        return (strtotime($time)+date('Z'));
    }
}