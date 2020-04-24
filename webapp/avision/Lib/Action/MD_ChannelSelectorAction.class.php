<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/4/21
 * Time: 16:53
 * 频道选择部件
 */
require_once(LIB_PATH.'Model/AgentModel.php');
require_once(LIB_PATH.'Model/ChannelModel.php');

class MD_ChannelSelectorAction extends Action {
    function __construct() {
        parent::__construct();
        session_start();
    }

    /**
     * 输出按机构、播主、频道名称的频道查询窗口，查询结构显示在选择频道下拉框中
     * 在下拉框选中频道后，向父窗口发送“chnSelected”消息，消息包含选中频道row对象{id:"id",name:"name"}
     * @param string $contextToken  当前的sessionid
     * @param array $right    操作权限$right['A']=true可以查看所有频道，$right["G"]=true可查看本机构的频道，无权限只能查看属主为自己的频道
     * @param string $account  当前登录用户账号
     * @example 在模板中嵌入
     *  <div id="blkFilter">
     *   {:R('MD_ChannelSelector/showfilter',array( $contextToken, $right, "$account" )) }
     *  </div>
     *  <script>
     *      $("#blkFilter").on("chnSelected",function(event,row){
     *          do sth
     *      });
     *  </script>
     */
    public function showfilter($contextToken,$right,$account){
        if($contextToken!=session_id()){
            echo "非法调用";
            return;
        }
        $webVar=array("contextToken"=>$contextToken);
        //取机构列表
        $dbAgent=D("agent");
        $agentList=$dbAgent->getNameList();
        $empItem=array('id'=>0, 'name'=>'　');
        if(is_array($agentList)) array_unshift($agentList,$empItem);
        else $agentList=array($empItem);
        $webVar["agentReadonly"]=($right["A"])?"false":"true";
        $agentListJson=str_replace('"',"'",json_encode2($agentList));
//var_dump($agentListJson);
        $webVar['agentListJson']=$agentListJson;

        $webVar['owner']=$account;
        $webVar["ownerReadonly"]=($right["A"]||$right["G"])?"false":"true";
        $webVar["chnid"]="";
        $this->assign($webVar);
        $this->display('MD_ChannelSelector:showfilter');
    }

    /**
     * 根据查询条件输出频道符合combobox数据要求的频道列表
     */
    public function getChnListJson(){
        $filterTpl=array('contextToken'=>'','agent'=>-1, 'owner'=>'none', 'chnName'=>'');
        $webVar=ouArrayReplace($filterTpl,$_POST,"org");

        if($webVar['contextToken']!=session_id()){
            echo '[{"id":0,"name":"参数错误"}]';
            return;
        }
        if(!empty($webVar["owner"])){
            $dbUser=D('user');
            $ownerid=$dbUser->getUserId($webVar["owner"]);
        }else $ownerid=0;
        //var_dump($webVar,$ownerid);
        $dbChannel=D("channel");
        $chnList=$dbChannel->getPulldownList($ownerid,'','',$webVar["chnName"],$webVar["agent"]);

        if(null==$chnList) echo "[]";
        else echo json_encode2($chnList);
    }
}