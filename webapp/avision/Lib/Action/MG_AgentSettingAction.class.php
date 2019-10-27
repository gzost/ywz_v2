<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/4/2
 * Time: 19:01
 * 机构配置，向平台管理员提供机构attr,config的数据配置界面
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once LIB_PATH.'Model/AgentModel.php';



class MG_AgentSettingAction extends AdminBaseAction{

    /**
     * 用户CURD统一入口，避免过多public function权限配置困难
     * {"operation":[{"text":"管理本机构","val":"R"},{"text":"管理所有机构","val":"A"},{"text":"平台配置","val":"P"}]}
     * @param string $work
     * @param string $container 容器ID
     */
    private $agent=0;   //当前操作的机构代码
    private $container=null;    //模块的容器DOM id
    private $platformOp=false;  //是否有平台操作权限

    public function index($work='init',$container=null){
        if(!$this->isOpPermit('R')){
            echo "权限不足";
            return;
        }
        $this->agent=(empty($_POST['agent']))?$this->getUserInfo('agent'):$_POST['agent'];
        $this->platformOp=($this->isOpPermit('P'))?'true':'false';  //平台操作权限
        $this->container=$container;
//var_dump($this->agent,$this->container);
        switch ($work){
            case 'init':    //初始化显示页面
                $this->baseAssign();
                if(IsMobile()){
                    $webVar=$this->getShowVar();
                    $this->assign($webVar);
                    $this->display("index");
                }else{
                    $this->display("index_w");
                }
                break;
            case 'show':   //PC真正的工作页面
                $this->baseAssign();
                $webVar=$this->getShowVar();
                $this->assign($webVar);
                $this->display("index");
                break;
            case 'agentPara':
                $this->agentPara();
                break;

            case "platformPara":
                $this->platformPara();
                break;
        }

    }

    /**
     * 生成查询条件，以及相关显示内容
     */
    private function getShowVar(){

        $webVar=array('agent'=>$this->agent);  //前端显示数据数组


        $viewAll=$this->isOpPermit('A');    //是否具有管理所有用户的权限
        $webVar['fixOrg']= ($viewAll||$this->isAdmin)?'false':'true';    //前端能否修改机构条件
        //$webVar['fixOrg']='true';
        $webVar['platformOp']=($this->isOpPermit('P'))?'true':'false';  //平台操作
        //$webVar['canUpdate']=($this->isOpPermit('U'))?'true':'false';
        //$webVar['canCreate']=($this->isOpPermit('C'))?'true':'false';
        //$webVar['canDestroy']=($this->isOpPermit('D'))?'true':'false';

        //供显示的机构列表数据
        $dbAgent=D('agent');
        $agentList=$dbAgent->getNameList();
        $webVar['agentList']=str_replace('"',"'",json_encode2(array_values($agentList)));
        return $webVar;
    }

    /**
     * 响应前端的请求，输出机构管理员配置参数的数据
     * 当$_POST['row']非空时，说明前端已更改数据，需要更新对应的数据
     */
    private function agentPara(){
        $agent=$this->agent;
        if(1>$agent) { echo "参数错误。"; return; }
        $webVar=array('agent'=>$this->agent,'container'=>$this->container);  //前端显示数据数组//装载此输出的外部容器

        //机构管理员可设置的参数
        $agentAttrib=array(
            array("key"=>"userlevel", "name"=>"用户等级显示为", "gkey"=>"userfields", "group"=>"显示属性", "value"=>"等级", "editor"=>array("type"=>"textbox")),
            array("key"=>"viplevel", "name"=>"VIP等级显示为", "gkey"=>"userfields", "group"=>"显示属性", "value"=>"VIP等级", "editor"=>array("type"=>"textbox")),
            array("key"=>"experience", "name"=>"经验值显示为", "gkey"=>"userfields", "group"=>"显示属性", "value"=>"经验值", "editor"=>array("type"=>"textbox")),
            array("key"=>"idcard", "name"=>"证件号显示为", "gkey"=>"userfields", "group"=>"显示属性", "value"=>"身份证号", "editor"=>array("type"=>"textbox")),
            array("key"=>"company", "name"=>"工作单位显示为", "gkey"=>"userfields", "group"=>"显示属性", "value"=>"工作单位", "editor"=>array("type"=>"textbox")),
            array("key"=>"realname", "name"=>"真实姓名显示为", "gkey"=>"userfields", "group"=>"显示属性", "value"=>"真实姓名", "editor"=>array("type"=>"textbox")),
            array("key"=>"udef1", "name"=>"自定义字段1显示为", "gkey"=>"userfields", "group"=>"显示属性", "value"=>"自定义字段1", "editor"=>array("type"=>"textbox")),
            array("key"=>"company", "name"=>"工作单位列表", "gkey"=>"listfield", "group"=>"可选择字段列表定义", "value"=>"",
                "editor"=>array("type"=>"textbox","options"=>array("multiline"=>"true","height"=>"100")))
        );

        $msg='';
        $dbAgent=D('agent');
        //有数据更新
        if(is_array($_POST['rows'])) {
            //过滤掉非法字符
            $rows=array();
            foreach ($_POST['rows'] as $key=>$row){
                $row['value']= preg_replace("/[\x01-\x2b\x3a-\x40\x5b-\x60\'\{\|\}\~\\\]/","",$row['value']);
                $rows[$key]=$row;
            }
            $rt = updateAttributes($dbAgent, array("id" => $agent), $rows);
            if(false===$rt) $msg='数据更新失败';
            else $msg='数据更新成功';
        }

        //获取并填写机构属性
        $rt=fillExtAttr($dbAgent,array("id"=>$agent),$agentAttrib);
        $propertyData=str_replace('"',"'",json_encode2(array_values($agentAttrib)));

        $webVar['propertyData']=$propertyData;

        $webVar['msg']=$msg;
        $this->assign($webVar);
        $this->display('agentPara');
    }


    private function platformPara(){
        if(!$this->isOpPermit('P')){
            echo "权限不足。";
            return;
        }
        $agent=$this->agent;
        if(1>$agent) { echo "参数错误。"; return; }
        $webVar=array('agent'=>$this->agent,'container'=>$this->container);  //前端显示数据数组//装载此输出的外部容器
        $msg='';

        $platformAttrib=array(
            array("key"=>"userlimit", "name"=>"机构最大用户数", "gkey"=>"config", "group"=>"控制参数", "value"=>"10", "editor"=>array("type"=>"numberbox")),
            array("key"=>"channellimit", "name"=>"机构最大频道数", "gkey"=>"config", "group"=>"控制参数", "value"=>"1", "editor"=>array("type"=>"numberbox")),
            array("key"=>"home", "name"=>"自定义首页标识串", "gkey"=>"", "group"=>"其它", "value"=>"", "editor"=>array("type"=>"textbox"))
        );
        $dbAgent=D('agent');
        //有数据更新
        if(is_array($_POST['rows'])) {
            //过滤掉非法字符
            $rows=array();
            foreach ($_POST['rows'] as $key=>$row){
                $row['value']= preg_replace("/[\x01-\x2b\x3a-\x40\x5b-\x60\'\{\|\}\~\\\]/","",$row['value']);
                $rows[$key]=$row;
            }
            $rt = updateAttributes($dbAgent, array("id" => $agent), $rows,'config');
            if(false===$rt) $msg='数据更新失败';
            else $msg='数据更新成功';
        }

        //获取并填写机构属性
        $rt=fillExtAttr($dbAgent,array("id"=>$agent),$platformAttrib,'config');
        $platformData=str_replace('"',"'",json_encode2(array_values($platformAttrib)));

        $webVar['platformData']=$platformData;

        $webVar['msg']=$msg;
        $this->assign($webVar);
        $this->display('platformPara');
    }
}