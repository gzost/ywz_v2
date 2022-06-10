<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2022/6/8
 * Time: 13:01
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once LIB_PATH.'Model/RecordfileModel.php';
require_once LIB_PATH.'Model/DictionaryModel.php';
require_once APP_PATH.'../../secret/OuSecret.class.php';
require_once COMMON_PATH.'vod/vodBase.class.php';
require_once APP_PATH.'../public/alivoduploadsdk/Autoloader.php';

use vod\Request\V20170321 as vod;

class MG_VodMaintenanceAction extends AdminBaseAction
{
    const DICT_CATEGORY="VOD";  //数据字典上的分类
    const DICT_ITEM_FILL="fillDate";    //数据字典上最后扫描云资源的日期
    const DICT_ITEM_CLEAN="checkRec";   //验证过云上资源存在的最大记录ID
    const YWZBOZHUID=12;        //易网真代位播主ID，用于系统填写丢失的VOD记录，之后需要管理员把记录分配给真正的属主

    public function main($work=""){
        set_time_limit(7200);
        switch ($work){
            case "fill":
                $this->fill();
                break;
            case "clean":
                $this->clean();
                break;
            case "reset":
                $this->reset();
                break;
            default:
                $this->baseAssign();

                $this->show('main');
                break;
        }

    }

    /**
     * 扫描阿里云上的视频资源，若本地数据库没记录则补录，补录的记录归属于ywzbozhu(特殊用户，ID=12)
     * 为节约扫描时间，在dictionary中记录最后一次扫描的日期，新的扫描只扫描更新的记录。
     * 若有必要，可以reset掉最后扫描日期，进行完整扫描。
     */
    private function fill(){
        //echo str_replace(array("T","Z"),array(" "),"2022-05-08T00:00:00Z").'**'; return;
        $date=$this->getLastFillDate();
        $nowStr=date("Y-m-d H:i:s");
        OUwrite( "最后扫描云资源日期：{$date}<br>");
        $dbVod=D("recordfile");
        $vodclass=vodBase::instance(5);

        $pageNo=1;
        $pageSize=20;
        $para=array(
            "PageSize"=>$pageSize,
            "SortBy"=>"CreationTime:Desc",
            "Match"=>"CreationTime=('".$date."',)"
        );
//$rt=$vodclass->searchMedia(1,$para);
//dump($rt);
        do{
            $pageData=$vodclass->searchMedia($pageNo,$para);
            $totalRecs=$pageData["Total"];    //符合条件的记录总数
            OUwrite("<br>记录总数：{$totalRecs}，已读入第{$pageNo}页。<br>");

            //处理读入的一页数据
            foreach ($pageData["MediaList"] as $media){
                $video=$media["Video"];
                $VideoId=$video["VideoId"];
                OUwrite($video["Title"]."--(VideoId: {$VideoId})<br>");
                //查找是否存在本地记录
                $recid=$dbVod->where(array("playkey"=>$video["VideoId"]))->getField('id');

                if(null == $recid){
                    //记录不存在
                    $rec=array(
                        "createtime"=>str_replace(array("T","Z"),array(" "),$video["CreationTime"]),
                        "owner"=>self::YWZBOZHUID,
                        "channelid"=>0,
                        "size"=>ceil($video["Size"]/1024/1024),
                        "length"=>ceil($video["Duration"].""),
                        "playkey"=>$video["VideoId"]."",
                        "name"=>$video["Title"]."",
                        "descript"=>$video["Description"]."",
                        "site"=>5,
                        "progress"=>$nowStr."系统自动补录"
                    );
                    $recid=$dbVod->add($rec);
                    OUwrite("补录记录，ID={$recid}<br>");
                }
            }
            $pageNo++;
            usleep(20000); //暂停20毫秒
        }while(($pageNo-1)*$pageSize <$totalRecs );
        $this->updateFillDate();
        OUwrite("处理正常结束<br>");
    }

    /**
     * 扫描site=5的记录，清除已经丢失云端资源的VOD记录
     */
    private function clean(){
        $lastCheckedId=$this->getLastCheckedId();   //检查过的最大ID
        $maxId=$lastCheckedId;
        OUwrite("检查过的最大记录ID：{$lastCheckedId}<br>");
        $dbVod=D("recordfile");
        $dbDel=D("deletedlog");
        $vodclass=vodBase::instance(5);
        $page=1;
        do{
            OUwrite("读取第{$page}页...<br>");
            $keyList=$dbVod->where("id>".$lastCheckedId." and site=5 ")->order("id")->page($page,20)->getField("id,playkey");
            foreach ($keyList as $k=>$v){
                OUwrite("({$k}){$v}<br>");
                if($k>$maxId) $maxId=$k;
                $cloudInfo=$vodclass->getVideoInfo($v);
                if(null==$cloudInfo){
                    OUwrite("云端资源丢失，将清理记录<br>");
                    $vodrec=$dbVod->where("id=".$k)->find();
                    $rec=array(
                        "tablename"=>"recordfile",
                        "recordid"=>$k,
                        "deletetime"=>date("Y-m-d H:i:s"),
                        "record"=>json_encode2($vodrec)
                    );
                    //删除记录与加入到删除日志在同一事务里完成。
                    try{
                        $dbVod->startTrans();
                        $rt=$dbDel->add($rec);
                        if($rt==false) throw new Exception("增加删除log失败：".$dbDel->getLastSql());
                        $rt=$dbVod->where("id=".$k)->delete();
                        if($rt==false) throw new Exception("删除Vod记录失败：".$dbVod->getLastSql());
                        $dbVod->commit();
                    }catch (Exception $e){
                        $dbVod->rollback();
                        OUwrite($e->getMessage());
                    }
                }
            }
            $page++;
            usleep(100000); //暂停100毫秒
        }while(is_array($keyList));
        $this->updateLastCheckedId($maxId);
        OUwrite("更新MaxId={$maxId}，处理正常结束。<br>");
    }

    private function reset(){
        $db=D("Dictionary");
        $cond=array("category"=>self::DICT_CATEGORY,
            'ditem'=>array(array("EQ",self::DICT_ITEM_FILL),array("EQ",self::DICT_ITEM_CLEAN),'or'));
        $rt=$db->where($cond)->delete();
        if(false===$rt) OUwrite("重置失败".$db->getLastSql());
        else OUwrite("重置完成");
    }
    /**
     * 取ictionary中记录最后一次扫描的日期，若找不到记录返回1970-01-01
     */
    private function getLastFillDate(){
        $db=D("Dictionary");
        $date=$db->getAttr(self::DICT_CATEGORY,self::DICT_ITEM_FILL,false);
        if(strlen($date)<8) return '1970-01-01T00:00:00Z';
        else return $date;
    }

    /**
     * 更新Dctionary中记录最后一次扫描的日期
     */
    private function updateFillDate(){
        $today=date("Y-m-d")."T00:00:00Z";
        $db=D("Dictionary");
        $cond=array("category"=>self::DICT_CATEGORY, "ditem"=>self::DICT_ITEM_FILL);
        $rt=$db->where($cond)->find();
        if(is_array($rt)){
            $rec=array("attr"=>$today);
            $db->where($cond)->save($rec);
        }else{
            $rec=$cond;
            $rec["attr"]=$today;
            $rec["dname"]="最后扫描云资源日期";
            $db->add($rec);
        }
    }

    /**
     * 取ictionary中记录的已检查过的最大记录ID
     * @return int 检查过的最大记录ID
     */
    private function getLastCheckedId(){
        $db=D("Dictionary");
        $id=$db->getAttr(self::DICT_CATEGORY,self::DICT_ITEM_CLEAN,false);
        if($id=='[]'||$id=='') return 0;
        else return intval($id);
    }
    private function updateLastCheckedId($id=0){
        $id=intval($id);
        if($id<1) return;
        $db=D("Dictionary");
        $cond=array("category"=>self::DICT_CATEGORY, "ditem"=>self::DICT_ITEM_CLEAN);
        $oldId=$db->where($cond)->getField('attr');
        if(intval($oldId)>0){
            //已有旧记录
            $rec=array("attr"=>$id);
            $db->where($cond)->save($rec);
        }else{
            $rec=$cond;
            $rec['attr']=$id;
            $rec['dname']="检查过的最大录像记录ID";
            $db->add($rec);
        }
    }
}