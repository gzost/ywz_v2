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
    const DEFAULT_VIDEO_COVER='/player/default/images/start.jpg';   //默认的录像文件封面图片

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

    ///// 各之类必须实现的接口 //////

    /**
     * 取指定录像文件封面图片的URL
     * 以下参数不同的点播平台可能有不同的使用方法，也不一定都需要提供
     * @param int $recordid     录像数据库记录ID
     * @param string $videoid   视频ID或播放标识
     * @param string $path      视频所在相对路径或附加参数
     * @return string   视频封面URL
     */
    abstract public function getCoverUrl($recordid,$videoid,$path);

    /**
     * 取指定录像文件播放URL
     * 以下参数不同的点播平台可能有不同的使用方法，也不一定都需要提供
     * @param int $recordid     录像数据库记录ID
     * @param string $videoid   视频ID或播放标识
     * @param string $path      视频所在相对路径或附加参数
     * @return string   视频播放URL，无法获取返回''
     */
    abstract public function getVideoUrl($recordid,$videoid,$path);
}