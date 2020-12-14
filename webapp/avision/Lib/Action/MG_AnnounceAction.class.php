<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/12/6
 * Time: 21:15
 * 通知/消息管理界面
 * 通知/消息分以下类型
 *  平台通知-会出现在所有频道及机构首页上
 *  机构通知-会出现在指定的机构首页及机构属下的所有频道上
 *  播主通知-会出现在此播主拥有的所有频道上
 *  频道通知-仅出现在指定的频道上
 *
 * 系统推送通知-可能有以上的类型，不过是要相应的权限才可管理。
 */

require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
class MG_AnnounceAction extends AdminBaseAction
{
    private $params=array();

    /**
     * 模块功能的公共入口，以简化权限配置。
     * 处理以下POST变量
     *  func：需要真正调用的功能名称，不提供时默认init
     *
     * 权限：
     *  R-可发送操作者本人的播主通知/或频道通知(操作者是频道owner或anchor)，
     *  G-可发送操作者所在的机构通知，以及本机构所有播主、频道的通知
     *  A-可管理平台及所有机构通知，若同时具备G权限，可管理所有播主及频道的通知
     *  S-可管理系统推送通知
     */
    public function main(){
        $paramsTpl = array( "func" => "init");
        $this->params = ouArrayReplace($paramsTpl, $_POST);
        $this->params['uid'] = $this->userId();
        $this->params["agentid"] = $this->getUserInfo("agent"); //当前用户所在机构
        $this->params["rightA"] = $this->isOpPermit("A"); //可管理所有通知[true|false],转到模板后true=1,false=空白
        $this->params["rightG"] = $this->isOpPermit("G"); //可管理机构通知
        $this->params["rightS"] = $this->isOpPermit("S"); //可管理系统推送通知
        $func=$this->params["func"];
        $this->$func();
    }

    /**
     * 初始化主管理页面
     */
    private function init(){
        $this->baseAssign();
        $webVar=$this->params;
        $webVar["account"]=$this->getUserInfo('account');   //当前用户的账号
        $webVar["agentName"]=D("agent")->where("id=".$this->params["agentid"])->getField('name');
        $webVar["contextToken"]=session_id();   //上下文标识

        $this->assign($webVar);
        $this->display("init");
    }

    /**
     * 输出弹出查询窗口的内容，接受以下POST变量
     *  contextToken-上行文跟踪字串，必须要与当前sessionid一致
     *  cond_value  -初始查询条件字串，不同的查询可以有不同的使用方法
     *  dataUrl     -获得列表数据的URL
     *  itemName    -可选，显示在查询条件输入框前的字串。默认：查询条件
     *  header      -可选，查询列表表头数组。默认：array( array("name"=>"id","text"=>"ID"), array("name"=>"name","text"=>"名称") );
     *  agentid     -可选，当前选中的机构ID
     *  ownerid     -可选，当前选中的播主ID
     */
    public function selector($itemName="查询条件", $agentid=0, $ownerid=0){
        if($_POST["contextToken"] != session_id()){
            echo "非授权访问！";
            return;
        }

        $webVar=$this->params;
        $webVar["cond_value"]=$_POST["value"];  //初始查询条件
        $webVar["contextToken"]=session_id();   //上下文标识
        $webVar["itemName"]=$itemName;
        if(is_array($_POST["header"])) $webVar["header"]=$_POST["header"];
        else $webVar["header"]=array(
            array("name"=>"id","text"=>"ID"),
            array("name"=>"name","text"=>"名称")
        );
        $webVar["dataUrl"]=$_POST["dataUrl"];
        $webVar["agentid"]=$agentid;
        $webVar["ownerid"]=$ownerid;
//dump($webVar);
        $this->assign($webVar);
        $this->display("selector");
    }

    /**
     * 向机构查询弹窗datagrid提供数据
     * @param int $page
     * @param int $rows
     * @param string $cond_value
     * 输出：
     *  必须具备id,name字段，选中记录后这两个字段将被作为选中的值记录，其它字段自行定义仅作显示
     */
    public function getAgentListJson($page=1,$rows=10,$cond_value=""){
        if($_POST["contextToken"] != session_id()){
            echo "[]";
            return;
        }
         $cond=array();
        if(!empty($cond_value))  $cond['name']=array("like","%".$cond_value."%");
        $db=D("agent");
        $total=$db->where($cond)->count();
//echo $db->getLastSql();
        if(0==$total){
            echo "[]";
        }else{
            $records=$db->field("id,name")->where($cond)->page($page,$rows)->select();
            $result=array("total"=>$total,"rows"=>$records);
            echo json_encode2($result);
        }
    }

    /**
     * 向播主查询弹窗datagrid提供数据
     * @param int $page
     * @param int $rows
     * @param string $cond_value
     * @param int $agentid
     */
    public function getOwnerListJson($page=1,$rows=10,$cond_value="", $agentid=0){
        if($_POST["contextToken"] != session_id()){
            echo "[]";
            return;
        }

        $cond=array();
        if(!empty($agentid)) $cond['agent']=$agentid;
        $cond['bozhu']=array("in",array("normal","junior"));
        if(!empty($cond_value))  $cond['account|username']=array("like","%".$cond_value."%");
        $db=D("user");
        $total=$db->where($cond)->count();
//echo $db->getLastSql();
        if(0==$total){
            echo "[]";
        }else{
            $records=$db->field("id,account as name,username")->where($cond)->page($page,$rows)->select();
            $result=array("total"=>$total,"rows"=>$records);
            echo json_encode2($result);
        }
    }

    /**
     * 向频道查询弹窗datagrid提供数据
     * @param int $page
     * @param int $rows
     * @param string $cond_value
     * @param int $agentid
     * @param int $ownerid
     */
    public function getChannelListJson($page=1,$rows=10,$cond_value="",$agentid=0,$ownerid=0){
        if($_POST["contextToken"] != session_id()){
            echo "[]";
            return;
        }

        $cond=array();
        if(!empty($agentid)) $cond['agent']=$agentid;
        if(!empty($ownerid)) $cond['owner']=$ownerid;
        if(!empty($cond_value))  $cond['name']=array("like","%".$cond_value."%");
        $db=D("channel");
        $total=$db->where($cond)->count();
//echo $db->getLastSql();
        if(0==$total){
            echo "[]";
        }else{
            $records=$db->field("id,name")->where($cond)->page($page,$rows)->select();
            $result=array("total"=>$total,"rows"=>$records);
            echo json_encode2($result);
        }
    }

    /**
     * 向消息查询datagrid输出数据
     */
    private function getAnnounceJson(){
        if($_POST["contextToken"] != session_id()){
            echo "[]";
            return;
        }
        $paras=array("page"=>1,"rows"=>20,"agentid"=>0,"ownerid"=>0,"channelid"=>0,"announce"=>"");
        $paras=ouArrayReplace($paras,$_POST);
//var_dump($paras);
        $dbAnnounce=D("announce");
        $fields="id,btime,etime,type,systempush,content";
        $cond=array();
        switch ($_POST["range"]){
            case "A":   //全局消息
                $cond['agentid']=0;
                $cond['ownerid']=0;
                $cond['chnid']=0;
                break;
            case "G":   //机构消息
                //若未指定机构定义为输出所有指定了机构的消息
                $cond['agentid']=(empty($paras['agentid']))? array("NEQ",0):$paras['agentid'];
                break;
            case "O":   //播主消息
                //必须指定播主，否则无输出
                $cond['ownerid']=(empty($paras['ownerid']))? array("EQ",-1):$paras['ownerid'];
                break;
            case "C":
                $cond['chnid']=(empty($paras['channelid']))? array("EQ",-1):$paras['channelid'];
                break;
            default:
                $cond['id']=-1; //不可能命中的条件
                break;
        }
        if(!empty($paras['announce'])) $cond['content']=array('like','%'.$paras['announce'].'%');
        $total=$dbAnnounce->where($cond)->count();
//echo $dbAnnounce->getLastSql();
        if(0==$total) {
            echo "[]";
        }else{
            $records=$dbAnnounce->field($fields)->where($cond)->page($page,$rows)->select();
            $result=array("total"=>$total,"rows"=>$records);
            echo json_encode2($result);
        }
    }

    private function showRec(){
        //接收POST参数，若无传入采用默认值
        $paras=array("id"=>0,"range"=>"","agentid"=>0,"ownerid"=>0,"channelid"=>0,"announce"=>"");
        $paras=ouArrayReplace($paras,$_POST);
//$paras['channelid']=1098;
        $webVar=array();
        try{
            if($_POST["contextToken"] != session_id()) throw new Exception("非法调用");
            if(empty($paras['range'])) throw new Exception("缺少range参数");
            if(0==$paras['id']){
                //显示新增记录
                $rec=array("id"=>0,"btime"=>date("Y:m:d H:i"), "etime"=>date("Y:m:d H:i",time()+3600*24), "createtime"=>date("Y:m:d H:i")
                    ,"zone"=>2, "type"=>1, "systempush"=>0, "creater"=>$this->params['uid'], "createrName"=>$this->userName(),"content"=>"新消息"
                    ,"agentid"=>$paras['agentid'],"ownerid"=>$paras['ownerid'],"chnid"=>$paras['channelid']
                    ,"loop"=>3,"speed"=>100,"color"=>"#FFFFFF","backgrand"=>"#003366");
            }else{
                $rec=D('announce')->where('id='.$paras['id'])->find();
                if(empty($rec)) throw new Exception('找不到指定的记录。');
                $attr=json_decode($rec['attr'],true);
                if(is_array($attr)) $rec=array_merge($rec,$attr);
                if($rec['agentid']>0) $rec['agentName']=D('agent')->where('id='.$rec['agentid'])->getField('name');
                if($rec['ownerid']>0) $rec['ownerName']=D('user')->where('id='.$rec['ownerid'])->getField('username');
                if($rec['chnid']>0) $rec['channelName']=D('channel')->where('id='.$rec['chnid'])->getField('name');
                if($rec['creater']>0) $rec['createrName']=D('user')->where('id='.$rec['creater'])->getField('username');
            }
//dump($rec);
            switch ($paras['range']){
                case "C":
                    $rec['channelName']=D("channel")->where("id=".$rec['chnid'])->getField('name');
                    if(empty($rec['channelName'])) throw new Exception($rec['chnid']."找不到指定的频道。");
                    break;
                case "O":
                    break;
                case "G":
                    break;
                case "A":
                    break;
                default:
                    throw new Exception("Range参数错误。");
                    break;
            }
            $webVar['rec']=$rec;
            $webVar['contextToken']=session_id();
            $webVar['range']=$paras['range'];
//dump($webVar);
            $this->assign($webVar);
            $this->display("showRec");
        }catch (Exception $e){
            echo $e->getMessage();
            return;
        }
    }

    private function saveRec(){
        //记录模板，接收前端POST的值，缺少的删除
        $recTpl=array('btime'=>'','etime'=>'','zone'=>'2','content'=>'','type'=>1,'systempush'=>0);
        $attrTpl=array('loop'=>1,'speed'=>100,'color'=>'#FFFFFF','backgrand'=>'#003366','href'=>'','imgurl'=>'');
        $id=intval($_POST['id']);
        $successPara=array();   //成功时的附加参数，如新记录的ID等

        try{
            if($_POST["contextToken"] != session_id()) throw new Exception("非法调用");
            if(empty($_POST['range'])) throw new Exception("缺少range参数");

            $rec=ouArrayReplace($recTpl,$_POST,'unset');
            $attr=ouArrayReplace($attrTpl,$_POST,'unset');
            if(is_array($attr)) $rec['attr']=json_encode2($attr);
            $rec['creater']=$this->params['uid'];
            if(0===$id){
                //新增记录
                switch ($_POST['range']){
                    case 'C':
                        $rec['chnid']=intval($_POST['chnid']);
                        break;
                    case 'O':
                        $rec['ownerid']=intval($_POST['ownerid']);
                        break;
                    case 'G':
                        $rec['agentid']=intval($_POST['agentid']);
                        break;
                    default:
                        throw new Exception('不可识别的消息范围。');
                        break;
                }
                $newRecId=D('announce')->add($rec);
                if($newRecId==false) throw new Exception('新增记录失败。');
                $successPara['id']=$newRecId;
            }else{
                //修改旧记录
                $rt=D('announce')->where('id='.$id)->save($rec);
                if(false===$rt) throw new Exception('修改记录失败。');
                $successPara['id']=$id;
            }

            Oajax::successReturn($successPara);
        }catch (Exception $e){
            Oajax::errorReturn($e->getMessage());
        }
    }

    //删除记录
    private function removeRec(){
        try{
            if($_POST["contextToken"] != session_id()) throw new Exception("非法调用");
            $id=intval($_POST['id']);
            if(0>= $id) throw new Exception('缺少参数');
            $rt=D('announce')->where('id='.$id)->delete();
            if(false==$rt) throw new Exception('删除记录失败');
            Oajax::successReturn();
        }catch (Exception $e){
            Oajax::errorReturn($e->getMessage());
        }
    }

    public function test(){
        $this->baseAssign();
        $this->display();
    }
}