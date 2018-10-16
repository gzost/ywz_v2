<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2018-6-7
 * Time: 12:11
 * 易网真系统集成端工具，通过SI客户端的支持
 */

class ywzSISDK
{
    const EFTIME=10;		//时间戳有效时长（秒）

    private $siAccount;   //SI账号
    private $commkey;     //SI端与core的通讯密码
    private $coreUrl;
    public function __construct($account,$key,$coreUrl='http://www.av365.cn')   {
        $this->siAccount=$account;
        $this->commkey=$key;
        $this->coreUrl=$coreUrl;
    }

    /**
     * 通用请求发送
     * @param $urlPath
     * @return bool|string
     */
    public function sendRequest($urlPath){
        $tm=sprintf("%x",time()+self::EFTIME);
        $url=sprintf("%s%s?account=%s&sec=%s&tm=%s",$this->coreUrl,$urlPath, $this->siAccount, $this->mkSecret($urlPath,$tm), $tm);
        //echo $url;
        $ret=file_get_contents($url);
        //echo $ret;
        return $ret;
    }

    /**
     * 生成通讯校验MD5字串
     * @param $uri  调用的urlpath
     * @param $tm   有效时间戳 小写16进制字串
     * @return string
     */
    private function mkSecret($uri,$tm){
        return md5($this->commkey.$uri.$this->siAccount.$tm);
    }

    /**
     * 调用ywz core SI接口获取当前频道的HLS收流地址
     * @param $streamid 推流记录ID
     * @return bool|string ywz core SI接口返回的Json字串
     */
    public function getPlayUri($streamid){
        $urlPath='/home.php/SI/getPlayUri/streamid/'.$streamid;
        $tm=sprintf("%x",time()+self::EFTIME);
        $url=sprintf("%s%s?account=%s&sec=%s&tm=%s",$this->coreUrl,$urlPath, $this->siAccount, $this->mkSecret($urlPath,$tm), $tm);
//echo $url;
        $ret=file_get_contents($url);
        return $ret;
    }

    /**
     * 调用ywz core SI接口获取指定录像id的收流地址
     * @param $vodid
     * @return bool|string
     */
    public function getVodUri($vodid){
        $urlPath='/home.php/SI/getVodUri/vodid/'.$vodid;
        $tm=sprintf("%x",time()+self::EFTIME);
        $url=sprintf("%s%s?account=%s&sec=%s&tm=%s",$this->coreUrl,$urlPath, $this->siAccount, $this->mkSecret($urlPath,$tm), $tm);
        //echo $url;
        $ret=file_get_contents($url);
        //echo $ret;
        return $ret;
    }

    /**
     * 检查指定的流是否在推流
     * @param $streamid
     * @return bool true-推流中
     */
    public function isStreamActive($streamid){
        $urlPath='/home.php/SI/getStreamList/streamid/'.$streamid;
        $json=json_decode($this->sendRequest($urlPath),true);

        if(isset($json['stream'][0]) && 'true'==$json['stream'][0]['isactive']) return true;
        else return false;
    }

}