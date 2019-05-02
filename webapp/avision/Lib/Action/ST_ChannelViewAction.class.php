<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/4/27
 * Time: 21:41
 * 统计指定时间区间，指定频道，观看的观看时长。包括：频道直播与频道点播
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once LIB_PATH."Model/ChannelModel.php";
require_once LIB_PATH."Model/UserModel.php";
require_once LIB_PATH."Model/AgentModel.php";
require_once LIB_PATH."Model/StatchannelviewsModel.php";
require_once APP_PATH.'../public/exportExecl.php';

class ST_ChannelViewAction extends AdminBaseAction{

    /**
     * 统计观众观看本频道所有节目的累计时长，包括直播和点播
     *
     * "operation":[{"text":"查询所属频道","val":"R"},{"text":"查询所有频道","val":"A"}]
     */
    public function main(){
        $work=$_REQUEST["work"];
        //设置公共web变量
        $webVar=array();
        $webVar['viewAll']=($this->isOpPermit('A'))?"true":"false"; //是否锁定owner
        switch ($work){
            case "chnSearch":   //查找频道列表，并输出观看统计列表datagrid
                $this->showListSearch($webVar);
                break;
            case "listSearch":
                $this->showListDatagrid($webVar);
                break;
            case "getList":
                $this->getDatagridData();
                break;
            case "saveExcel":
                $this->getDatagridData(true);
                break;
            case "sort":    //暂时无用
                 setPara("STC_listViewsSort",$_POST["sort"]." ".$_POST["order"]);
                break;
            default:    //初始化
                $this->baseAssign();
                $this->assign('mainTitle','频道观看时长统计');

                $webVar["bozu"]=$this->getUserInfo("account");
                $this->assign($webVar);
                $this->display('main');
        }

    }

    private function showListSearch($webVar){
        //组织频道查询条件
        //GetNameJason($searchKey, $owner = 0, $fmt = 'array')
        $dbUser=D("user");
        $dbChannel=D("channel");
        if("false"==$webVar["viewAll"]) $owner=$this->userId();
        else{
            if(!empty($_POST["bozu"])) {
                $bozu=$_POST["bozu"];
                $owner=$dbUser->getUserId($bozu);
            }else{
                $owner=0;
            }
        }
        $chnName=(isset($_POST["chnName"]))?trim($_POST["chnName"]):"";
        $chnList=$dbChannel->GetNameJason($chnName,$owner);
        if(count($chnList)>1){
            $webVar["chnId"]=$chnList[0]['id'];
            $chnListJson=json_encode2($chnList);
            $chnListJson=str_replace('"',"'",$chnListJson);
        }else{
            $webVar["chnId"]='';
            $chnListJson="[]";
        }
        $webVar["editable"]="true";
        $webVar["chnListJson"]=$chnListJson;
        $webVar["beginTime"]=date("Y-01-01");
        $webVar["endTime"]=date("Y-m-d");
        $this->assign($webVar);
        $this->display("showListSearch");
    }

    private function showListDatagrid($webVar){
        if(empty($_POST["chnId"])){
            echo "必须选择频道！";
            return;
        }
        $chnId=$_POST["chnId"];
        $webVar["chnId"]=$chnId;
        $webVar["viewerAccount"]=$_POST["viewerAccount"];
        $webVar["beginTime"]=$_POST["beginTime"];
        $webVar["endTime"]=$_POST["endTime"];
        $dbAgent=D("agent");
        $agent=0;   //TODO:取频道对应的机构
        $fieldName=$dbAgent->getUserFieldName($agent);
        $header=array();
        $header[]=array('name'=>'userid','text'=>$fieldName['id'],'data-options'=>"width:100,align:'right', halign:'center',sortable:'true'");
        $header[]=array('name'=>'account','text'=>$fieldName['account'],'data-options'=>"width:100,align:'left', halign:'center'");
        $header[]=array('name'=>'username','text'=>$fieldName['username'],'data-options'=>"width:200,align:'left', halign:'center'");
        $header[]=array('name'=>'phone','text'=>$fieldName['phone'],'data-options'=>"width:200,align:'left', halign:'center'");
        $header[]=array('name'=>'duration','text'=>'观看时长','data-options'=>"width:100,align:'right', halign:'center',sortable:'true' ");
        //叠加会员问题字段
        $dbchannel=D('channel');
        $chnAttr=$dbchannel->getAttrArray($chnId);
        $quest=$chnAttr['signQuest'];
        foreach ($quest as $v){
            $header[]=array('name'=>$v,'text'=>htmlspecialchars($v));
        }
        $webVar["header"]=$header;
        setPara("STC_listViews",$webVar);   //传递到datagrid数据提供模块或Excel下载模块
        setPara("STC_listViewsSort","duration desc");
        $this->assign($webVar);
        $this->display("showListDatagrid");
    }

    private function getDatagridData($export=false){
        $webVar=getPara("STC_listViews");
        //var_dump($export);
        $dbUser=D("user");
        //$dbChannel=D("channel");
        $dbStat=D("statchannelviews");

        //组织查询条件
        $cond=array("chnid"=>$webVar["chnId"]);
        if(!empty($webVar["viewerAccount"])){
            $viewId=$dbUser->getUserId($webVar["viewerAccount"]);
            if($viewId>0) $cond["userid"]=$viewId;
        }
        if(empty($webVar["beginTime"]) || !strtotime($webVar["beginTime"])) $webVar["beginTime"]="1000-01-01";
        if(empty($webVar["endTime"]) || !strtotime($webVar["endTime"])) $webVar["endTime"]="7000-12-31";
        $cond['rq']=array("between",array($webVar["beginTime"],$webVar["endTime"]));

        //总记录数
        $subQuery=$dbStat->field("count(*)")->where($cond)->group("userid")->select(false);
//echo $subQuery;
        $totalRecs=$dbStat->table($subQuery.'a')->getField("count(*)");
        //$totalRecs=$dbStat->where($cond)->getField("count(*)");
//echo $dbStat->getLastSql();
//dump($totalRecs);
        if(0==$totalRecs){
            echo [];
        }else{
            //合计
            $total=$dbStat->where($cond)->Sum("duration");
            //TODO: 排序处理
            $sort=getPara("STC_listViewsSort");
            if(empty($sort)) $sort="duration desc";

            //分页处理
            $page=$_POST["page"];   //需要的页面值，首页是1
            if($page>0){    //有分页
                $rows=$_POST["rows"];
                if($rows<1) $rows=10;   //无设置或无效设置，设为默认每页10行
                $records=$dbStat->field("userid,sum(duration) as duration")->where($cond)->group("userid")->order($sort)->page($page,$rows)->select();
            }else{
                $records=$dbStat->field("userid,sum(duration) as duration")->where($cond)->group("userid")->order($sort)->select();
            }
//echo $dbStat->getLastSql();
            //填充相关字段
            $chnid=$webVar["chnId"];
            $fields="u.account,u.username,u.phone,u.idcard,u.company,u.realname,C.note";
            foreach ($records as $key=>$row){
                $userid=$row["userid"];
                $rec=$dbUser->alias('u')->field($fields)->where(array("u.id"=>$userid))->join(C("DB_PREFIX")."channelreluser C on C.chnid=$chnid and C.uid=u.id and type='会员'")->find();
//echo $dbUser->getLastSql();
                $memberInfo=json_decode($rec["note"],true);
                foreach ($memberInfo as $v){
                    $rec[$v["quest"]]=$v["answer"];
                }
                unset($rec["note"]);
//var_dump($rec);
                foreach ($rec as $k=>$v){
                    $records[$key][$k]=$v;
                }
            }
            $result=array();
            $result["rows"]=$records;
            $result["total"]=$totalRecs;
            $result["footer"][]=array("phone"=>"合计", "duration"=>$total);
            if($export){
                $result['header'][]=$webVar["header"];
                $result['title'][]=array('text'=>$webVar['beginTime'].'至'.$webVar['endTime'].'频道观看时长统计','size'=>16);
                $result['defaultFile']='频道观看时长统计.xlsx';
//dump($result);
                exportExecl($result);
            }else{
                echo json_encode2($result);
            }

        }
        return;
    }
}