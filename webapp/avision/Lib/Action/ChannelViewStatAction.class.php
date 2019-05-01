<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/4/28
 * Time: 14:16
 * 后台定时执行的统计功能。统计从上次统计结束至本次统计开始时各频道的观看时长
 */

require_once APP_PATH.'../public/Authorize.Class.php';
require_once(LIB_PATH.'Model/DictionaryModel.php');
require_once(LIB_PATH.'Model/OnlinelogModel.php');
require_once(LIB_PATH.'Model/StatchannelviewsModel.php');

class ChannelViewStatAction extends Action{
    protected $opStr='';    //当前用户的可操作字串

    function __construct(){
        parent::__construct();

        C('LOG_FILE','chnviewstat%m%.log');

        $str=sprintf("======= BEGIN stat channel views %s =========",date("m-d H:i:s"));
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

        //die('debug!');
    }

    /**
     * 统计从上次统计结束至本次统计开始时各频道的观看时长。一般每天统计一次。
     * 数据来源：onlinelog
     * 
     */
    public function statistics(){
        set_time_limit(7200);
        ini_set('memory_limit', '2048M');
        //1、取上次处理onlinelog的最后记录ID
        $dbDict=D("dictionary");
        $lastLogId=$dbDict->getChnViewLastId(); //上次统计onlinelog最后的
//var_dump($lastLogId);
        //$dbDict->setChnViewLastId(intval($lastLogId)+1);
        //2、取当前onlinelog的最大ID
        $dbOnlinelog=D("Onlinelog");
        $maxLogId=$dbOnlinelog->Max("id");
//var_dump($maxLogId);
        //$maxLogId=30000;  //for test
        $procRecsPerSection=10000;    //每次循环处理的记录数
        $dbStatChnViews=D("statchannelviews");

        //3、循环，每次处理1万条记录，避免过多占用内存及CPU负载过高
        $maxProId=$lastLogId;
        for ($beginProcId=$lastLogId+1; $maxLogId>=$beginProcId; $beginProcId+=$procRecsPerSection){
            $maxProId=$beginProcId+$procRecsPerSection;
            if($maxProId > $maxLogId) $maxProId=$maxLogId;  //本次循环最大处理的ID
echo "befor query:".memory_get_usage();
            logfile("开始处理：$beginProcId 到 $maxProId ", LogLevel::NOTICE);
            $sql="select L.userid,activetime,beginview,".
                " case objtype when 'live' then L.refid ".
                "  when 'vod' then R.channelid  else 0 end as chnid, ".
                " activetime-beginview as duration from __PEEFIX__onlinelog L ".
                " left join __PEEFIX__recordfile R on refid=R.id and R.channelid>0".
                " where L.id>=$beginProcId and L.id<=$maxProId ";
            $sql=str_replace("__PEEFIX__",C("DB_PREFIX"),$sql);
            $records=$dbOnlinelog->query($sql);
            if(false===$records){
                logfile("DB error:".$sql, LogLevel::EMERG);
                exit;
            }
echo " after:".memory_get_usage();
            //4、逐条处理onlinelog统计到 statchannelviews 中
            $dbStatChnViews->startTrans();
            try{
                foreach ($records as $row){
                    $row['rq']=date("Y-m-d",$row['beginview']);
                    if($row['rq']<"2018-01-01") continue;   //2018年之前的不统计
//var_dump($row);
                    $rt=$dbStatChnViews->inserUpdate($row);
//var_dump($rt);
                }
                $rt=$dbDict->setChnViewLastId($maxProId);
                if($rt===false) throw new Exception("更新数据字典失败。");
            }catch (Exception $e){
                $dbStatChnViews->rollback();
                logfile("rollback:".$e->getMessage(),LogLevel::ALERT);
                exit;
            }
            $dbStatChnViews->commit();
echo " befor unset:".memory_get_usage();
            unset($row);
echo " unseted row:".memory_get_usage();
            unset($records);    //主动释放内存
echo " unset:".memory_get_usage()."\n";
            sleep(1);   //让服务器喘会气
        }

        logfile("=== Statistics END ====",LogLevel::NOTICE);
    }
}
?>