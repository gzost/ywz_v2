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
        $header[]=array('name'=>'account','text'=>$fieldName['account'],'data-options'=>"width:100,align:'left', halign:'center'");
        $header[]=array('name'=>'username','text'=>$fieldName['username'],'data-options'=>"width:200,align:'left', halign:'center'");
        $header[]=array('name'=>'phone','text'=>$fieldName['phone'],'data-options'=>"width:200,align:'left', halign:'center'");
        $header[]=array('name'=>'duration','text'=>'观看时长','data-options'=>"width:100,align:'right', halign:'center'");
        //叠加会员问题字段
        $dbchannel=D('channel');
        $chnAttr=$dbchannel->getAttrArray($chnId);
        $quest=$chnAttr['signQuest'];
        foreach ($quest as $v){
            $header[]=array('name'=>$v,'text'=>htmlspecialchars($v));
        }
        $webVar["header"]=$header;
        setPara("STC_listViews",$webVar);   //传递到datagrid数据提供模块或Excel下载模块
        $this->assign($webVar);
        $this->display("showListDatagrid");
    }

    private function getDatagridData(){
        $webVar=getPara("STC_listViews");
        //var_dump($_POST);
        $dbUser=D("user");
        $dbChannel=D("channel");
        $dbStat=D("statchannelviews");

        //组织查询条件
        $cond=array("chnid"=>$webVar["chnId"]);
        if(!empty($webVar["viewerAccount"])){
            $viewId=$dbUser->getUserId($webVar["viewerAccount"]);
            if($viewId>0) $cond["userid"]=$viewId;
        }
        if(!empty($webVar["beginTime"])) $cond['rq']=array("EGT",$webVar["beginTime"]);
        if(!empty($webVar["endTime"])) $cond['rq']=array("ELT",$webVar["endTime"]);

        //查询语句
        $sql="select userid,sum(duration) as duration from av2_statchannelviews group by userid order by userid ";
        //TODO: 排序处理
        //分页处理
        $page=$_POST["page"];   //需要的页面值，首页是1
        if($page>0){    //有分页
            $rows=$_POST["rows"];
            if($rows<1) $rows=10;   //无设置或无效设置，设为默认每页10行
            $sql .=" limit ".($page-1)*$rows.",".$rows;
        }
        $records=$dbStat->query($sql);
        dump($records);
    }
}