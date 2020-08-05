<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/4/27
 * Time: 10:41
 */

require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once(LIB_PATH.'Model/UserModel.php');

class MG_chatAction extends AdminBaseAction{
    private $params=array();

    /**
     * 权限"operation":[{"text":"管理自己频道","val":"R"},{"text":"管理所有频道","val":"A"},{"text":"管理所在机构频道","val":"G"},{"text":"删除记录","val":"D"}]
     */
    public function main()  {
        $paramsTpl = array( "chnid" => 0, "chnName" => "", "func" => "init");
        $this->params = ouArrayReplace($paramsTpl, $_POST);
        $this->params['uid'] = $this->userId();
        $this->params["agent"] = $this->getUserInfo("agent"); //当前用户所在机构
        $this->params["rightA"] = $this->isOpPermit("A"); //可管理所有频道[true|false]
        $this->params["rightG"] = $this->isOpPermit("G"); //可管理机构频道
        $this->params["rightS"] = $this->isOpPermit("R"); //R是管理自己频道功能，程序中用S做权限，这是基本的操作功能
        $func=$this->params["func"];
        $this->$func();
    }

    /**
     * 输出成绩编辑主界面
     */
    private function init() {
        $this->baseAssign();
        $webVar = $this->params;
        $webVar["right"]["A"]=$this->isOpPermit("A");
        $webVar["right"]["G"]=$this->isOpPermit("G");
        $webVar["right"]["S"]=$this->isOpPermit("R");
        $webVar["account"]=$this->getUserInfo('account');   //当前用户的账号
        $webVar["contextToken"]=session_id();   //上下文标识
        //默认的查询条件
        $webVar["isshow"]="wait";   //默认查询审核中的记录
        $webVar["senderAccount"]="";
//dump($webVar);
        //定义显示表头
        $header=array(
            array("name"=>"ck", "text"=>"", "options"=>"checkbox:true"),
            array("name"=>"id", "text"=>"记录ID", "options"=>"width:80,align:'right',halign:'center'"),
            array("name"=>"account", "text"=>"发送人账号", "options"=>"width:160,align:'left',halign:'center'"),
            array("name"=>"sendername", "text"=>"发送人昵称", "options"=>"width:160,align:'left',halign:'center'"),
            array("name"=>"message", "text"=>"内容", "options"=>"width:400,align:'left',halign:'center',nowrap:false"),
            array("name"=>"isshow", "text"=>"显示状态", "options"=>"width:80,align:'center',formatter:function(value){
                if('wait'==value) return '待审核';
                else if('true'==value) return '显示';
                else if('false'==value) return '不显示';
                else return value;
            }"),
            array("name"=>"sendtime", "text"=>"发送时间", "options"=>"width:160,align:'center'")
        );
        $webVar["header"]=$header;
        $this->assign($webVar);
        $this->display("MG_chat/init");
    }

    /**
     * 取符合条件的聊天列表
     * POSR以下参数：isshow-状态，senderAccount-发送人账号，btime-发送起始时间, etime-发送结束时间, chnid-频道ID, contextToken-上下文令牌
     *  输出符合datagrid格式的json编码串。
     */
    private function getChatListJson(){
        //var_dump($_POST);
        try{
            if($_POST["contextToken"] != session_id()) throw new Exception("非法访问");
            $dbChat=D("webchat");
            $chnid=intval($_POST["chnid"]);
            if(empty($chnid)) throw new Exception("必须先选中频道");

            $where=array("chnid"=>$chnid);    //必须限于指定的频道

            if(!empty($_POST["isshow"])) $where["isshow"]=$_POST["isshow"];

            //指定账号
            if(!empty($_POST['senderAccount'])) $where["account"]=$_POST["senderAccount"];

            //提交时间
            if(!empty($_POST["btime"]) && !empty($_POST["etime"])){
                $where["A.sendtime"]=array("between",array($_POST["btime"],$_POST["etime"]));
            }elseif(!empty($_POST["btime"])){
                $where["A.sendtime"]=array("EGT",$_POST["btime"]);
            }elseif(!empty($_POST["etime"])){
                $where["A.sendtime"]=array("ELT",$_POST["etime"]);
            }

            $records=$dbChat->alias("A")->field("A.*, U.account")
                ->join(C("DB_PREFIX")."user U ON A.senderid=U.id")->where($where)->order("A.id")->limit(200)->select();
//var_dump($records);
//echo $dbChat->getLastSql();
            if(empty($records)) echo "[]";
            else{
                foreach ($records as $key=>$row)
                    $records[$key]["message"]=htmlspecialchars($row["message"]);
                $result=array("rows"=>$records);
                echo json_encode2($result);
            }
        }catch (Exception $e){
            echo "[]";
            return;
        }
    }

    //post内容：{ func:"update",click:func,chnid:params.chnid, contextToken:params.contextToken, recs:recs}
    private function update(){
        //var_dump($_POST);
        try{
            if($_POST["contextToken"]!=session_id()) throw new Exception("非法操作");

            $click=$_POST["click"];
            if($click=="btnShow" || $click=="btnNoShow"){
                $idstr=implode(",",array_column($_POST["recs"],"id"));  //要转换记录的id 串
                $rec=array("isshow"=>($click=="btnShow")?"true":"false");
                $rt=D("webchat")->where(array("id"=>array("in",$idstr)))->save($rec);
                if(false===$rt) throw new Exception("数据库写入失败。");
            }elseif ($click=="btnNoChat" || $click=="btnCanChat"){
                $chnid=$_POST["chnid"];
                if(empty($chnid)) throw new Exception("缺少频道参数");
                $rec=array("chnid"=>$chnid,"uid"=>$_POST["recs"][0]["senderid"]);   //禁言记录
                if(empty($rec["uid"])) throw new Exception("缺少用户参数");
                if($click=="btnNoChat"){
                    $rt=D("channelnochat")->add($rec);
                }else{
                    $rt=D("channelnochat")->where($rec)->delete();
                }
            }else throw new Exception("未知的功能请求");
            Oajax::successReturn();
        }catch (Exception $e){
            Oajax::errorReturn($e->getMessage());
        }
    }

    /////以下方法由update调用
}