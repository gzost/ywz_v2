<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/4/27
 * Time: 10:41
 */

require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once(LIB_PATH.'Model/UserModel.php');

class MG_scoreAction extends AdminBaseAction{
    private $params=array();

    /**
     * 权限"operation":[{"text":"管理自己频道","val":"R"},{"text":"管理所有频道","val":"A"},{"text":"管理所在机构频道","val":"G"},{"text":"编辑成绩","val":"S"}]
     */
    public function main()  {
        $paramsTpl = array( "chnid" => 0, "chnName" => "", "func" => "init");
        $this->params = ouArrayReplace($paramsTpl, $_POST);
        $this->params['uid'] = $this->userId();
        $this->params["agent"] = $this->getUserInfo("agent"); //当前用户所在机构
        $this->params["rightA"] = $this->isOpPermit("A"); //可管理所有频道[true|false]
        $this->params["rightG"] = $this->isOpPermit("G"); //可管理机构频道
        $this->params["rightS"] = $this->isOpPermit("S"); //可编辑成绩
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
        $webVar["right"]["S"]=$this->isOpPermit("S");
        $webVar["account"]=$this->getUserInfo('account');   //当前用户的账号
        $webVar["contextToken"]=session_id();   //上下文标识
//dump($webVar);
        //定义显示表头
        $header=array(
            array("name"=>"exerciseid", "text"=>"练习ID", "options"=>"width:80,align:'right',halign:'center'"),
            array("name"=>"title", "text"=>"练习名称", "options"=>"width:200,align:'left',halign:'center'"),
            array("name"=>"username", "text"=>"用户昵称", "options"=>"width:160,align:'left',halign:'center'"),
            array("name"=>"realname", "text"=>"真实姓名", "options"=>"width:100,align:'left',halign:'center'"),
            array("name"=>"answer", "text"=>"答案", "options"=>"width:200,align:'left',halign:'center',nowrap:false,
                formatter:function(value,row,index){ 
                    return '<div class=ansercolume >'+value+'</div>'}"),
            array("name"=>"score", "text"=>"得分", "options"=>"width:80,align:'left',halign:'center',editor:{type:'numberbox'}")
        );
        $webVar["header"]=$header;
        $this->assign($webVar);
        $this->display("MG_score/init");
    }

    /**
     * 取符合条件的答案列表
     * POSR以下参数：exercise-练习ID或标题，saccount-学员账号，btime-交卷起始时间, etime-交卷结束时间, chnid-频道ID, contextToken-上下文令牌, page-取第几页结果, rows-每页记录数
     *  输出符合datagrid格式的json编码串。
     */
    private function getScoreListJson(){
        //var_dump($_POST);
        try{
            if($_POST["contextToken"] != session_id()) throw new Exception("非法访问");
            $dbAnswer=D("answer");
            $chnid=intval($_POST["chnid"]);
            if(empty($chnid)) throw new Exception("必须先选中频道");

            $where=array("E.chnid"=>$chnid);    //必须限于指定的频道

            //指定学员账号
            if(!empty($_POST['saccount'])){
                $uid=D("user")->getUserId($_POST['saccount']);
                if(!empty($uid)) $where["A.userid"]=$uid;
            }

            //交卷时间
            if(!empty($_POST["btime"]) && !empty($_POST["etime"])){
                $where["A.answertime"]=array("between",array($_POST["btime"],$_POST["etime"]));
            }elseif(!empty($_POST["btime"])){
                $where["A.answertime"]=array("EGT",$_POST["btime"]);
            }elseif(!empty($_POST["etime"])){
                $where["A.answertime"]=array("ELT",$_POST["etime"]);
            }

            //练习名称或ID
            $exercise=$_POST["exercise"];
//var_dump(($exercise[0]=="#"),trim($exercise,"#")) ;
            if(!empty($_POST["exercise"])){
                if($exercise[0]=="#"){
                    //以练习编号为条件
                    $exid=intval(trim($exercise,"#"));
                    if($exid>0) $where["A.exerciseid"]=$exid;
                }else{
                    //以练习标题为条件
                    $where["E.title"]=array("like","%{$exercise}%");
                }
            }
            //分页
            $page=(!empty($_POST["page"]))? intval($_POST["page"]):1;
            $rows=(!empty($_POST["rows"]))? intval($_POST["rows"]):1;

            $total=$dbAnswer->alias("A")->join(C("DB_PREFIX")."exercise E ON A.exerciseid=E.id")
                ->join(C("DB_PREFIX")."user U ON A.userid=U.id")->where($where)->count();
            if(1>$total) throw new Exception("没有符合条件的数据");

            $records=$dbAnswer->alias("A")->field("A.*, E.title, E.qt, U.username, U.realname, U.company")->join(C("DB_PREFIX")."exercise E ON A.exerciseid=E.id")
                ->join(C("DB_PREFIX")."user U ON A.userid=U.id")->where($where)->order("exerciseid")->page($page,$rows)->select();
//var_dump($records);
//echo $dbAnswer->getLastSql();
            foreach ($records as $key=>$row)
                $records[$key]["answer"]=htmlspecialchars($row["answer"]);
            $result=array("rows"=>$records, "total"=>$total);
            echo json_encode2($result);
        }catch (Exception $e){
            echo "[]";
            return;
        }
    }

    public function update(){
        var_dump($_POST);
        $id=intval($_POST["id"]);
        $newRec=array("score"=>$_POST["score"]);
        $rt=D("answer")->where("id=".$id)->save($newRec);
        json_encode2($_POST);
    }
}