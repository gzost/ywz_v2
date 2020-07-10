<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/7/10
 * Time: 9:30
 * YWZ自身点播平台
 */
require_once APP_PATH.'../public/Ou.Function.php';
require_once LIB_PATH.'Model/RecordfileModel.php';

class vodSite1 extends vodBase{

    /**
     * 取指定录像文件封面图片的URL
     * @param int $recordid     忽略
     * @param string $videoid   忽略
     * @param string $path      文件存储相对路径
     * @return string
     */
    public function getCoverUrl($recordid,$videoid,$path){
        if(empty($path)) $url=self::DEFAULT_VIDEO_COVER;
        else{
            $basepath=(''==C(vodfile_base_path))?'/vodfile':C(vodfile_base_path);
            $patter = "/.mp4$/";
            $rep = '.jpg';
            $path = preg_replace($patter, $rep, $path);
            //录像文件存放系统路径
            $fullPath=$_SERVER["DOCUMENT_ROOT"].$basepath.$path;
            $url=(is_file($fullPath)) ? ($basepath.$path): self::DEFAULT_VIDEO_COVER;
        }
        return $url;
    }

    /**
     * @param int $recordid     数据库记录ID
     * @param string $videoid   忽略
     * @param string $path      忽略
     * @return string
     */
    public function getVideoUrl($recordid,$videoid,$path){
        return D("recordfile")->getVodMrl($recordid);
    }
}