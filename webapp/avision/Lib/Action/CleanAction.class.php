<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2022/6/6
 * Time: 15:30
 * 后台定时执行的清理任务，通常每天运行一次
 */
require_once APP_PATH.'../public/Authorize.Class.php';
require_once(LIB_PATH.'Model/DictionaryModel.php');
require_once(LIB_PATH.'Model/OnlinelogModel.php');
require_once APP_PATH.'../public/Mutex.class.php';

class CleanAction extends Action
{
    protected $opStr='';    //当前用户的可操作字串

    function __construct(){
        parent::__construct();

        C('LOG_FILE','cleanAction%m%.log');
        C('LOGFILE_LEVEL',LogLevel::NOTICE);
        $str=sprintf("======= BEGIN clean %s =========",date("m-d H:i:s"));
        logfile($str,LogLevel::NOTICE);

        session_start();
        //进行任何结算之前先登录结算用户
        $author = new authorize();

        $account=getPara('account');
        $password=getPara('password');

        if(!$author->isLogin(C('OVERTIME'))){
            if(!$author->issue($account,md5($password))){
                logfile("you have no permit!",LogLevel::ALERT);
                die("you have no permit!\n\r");
            }
        }
        $this->opStr=$author->getOperStr(MODULE_NAME,ACTION_NAME);  //取可操作字串
        if(strlen($this->opStr)<1){
            logfile("you have no permit!!",LogLevel::ALERT);
            die("you have no permit!!\n\r");
        }
    }

    /**
     * 总入口
     */
    public function main(){
        set_time_limit(3600);
        //ini_set('memory_limit', '4096M');

        try{
            //排它锁定，保证只有一个清除进程在运行
            if(false==mutex::lock("clean"))  throw new Exception("mutex::lock false.");

            //1.清除半年前的applog
            $this->cleanApplog();

            //2.清除1年前的onlinelog
            $this->cleanOnlinelog();

            //3.清除无效的录像记录
            $this->cleanVodRecord();

            //4.清除过时的微信业务临时记录
            $this->cleanWxMessage();

        }catch (Exception $e){
            logfile($e->getMessage(),LogLevel::ALERT);
        }

        //解锁
        mutex::unlock("clean");
        logfile("=== clean END ====",LogLevel::NOTICE);
    }

    /**
     * 清除半年前的applog
     */
    private function cleanApplog(){
        $lastDay=date('Y-m-d',strtotime("-6 month"));   //半年前的今天
        $db=D("applog");
        $rt=$db->where(array("logtime"=>array("LT",$lastDay)))->delete();
        logfile($db->getLastSql(),LogLevel::SQL);
        logfile("清理了{$rt}条过时的applog记录。", LogLevel::NOTICE);
        //echo "<br>{$rt}record was cleaned.<br>";
    }

    /**
     * 清除1年前的onlinelog
     */
    private function cleanOnlinelog(){
        $lastStamp=strtotime(date('Y-m-d',strtotime("-1 year")));   //一年前的今天时间戳
        $db=D("onlinelog");
        $rt=$db->where(array("activetime"=>array("LT",$lastStamp)))->delete();
        logfile($db->getLastSql(),LogLevel::SQL);
        logfile("清理了{$rt}条过时的onlinelog记录。", LogLevel::NOTICE);
        //echo "<br>{$rt}record was cleaned.<br>";

    }

    /**
     * 清除无效的VOD文件记录
     */
    private function cleanVodRecord(){
        $db=D("recordfile");
        //1.删除8小时前建立，且没有playkey(视频没上传到阿里云)
        $lastTime=date('Y-m-d H:i:s',strtotime("-8 hour"));   //8小时前
        $rt=$db->where(array("createtime"=>array("LT",$lastTime),"playkey"=>""))->delete();
        logfile($db->getLastSql(),LogLevel::SQL);
        logfile("清理了{$rt}条无效的recordfile记录。", LogLevel::NOTICE);
        //echo "<br>{$rt}record was cleaned.<br>";

    }

    /**
     * 清除过时的微信业务临时记录
     */
    private function cleanWxMessage(){
        $db=D("Message");
        $lastStamp=strtotime(date('Y-m-d',strtotime("-6 month")));  //6个月前的时间戳
        $rt=$db->where(array("createtime"=>array("LT",$lastStamp)))->delete();
        logfile($db->getLastSql(),LogLevel::SQL);
        logfile("清理了{$rt}条过期的message记录。", LogLevel::NOTICE);

    }
}