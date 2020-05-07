<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/4/25
 * Time: 11:58
 * 学习功能的前端界面控制器
 */

require_once APP_PATH.'../public/Ou.Function.php';
require_once(LIB_PATH.'Model/ExerciseModel.php');
class FE_exerciseAction extends Action{
    function __construct() {
        parent::__construct();
        session_start();
    }

    /**
     * 输出初始化课后练习显示界面
     * POST参数：
     *  - uid   int 用户ID
     *  - chnid int 频道ID
     *  - vodid int
     *  - type  int 题目类型，目前只支持2-堂上练习
     *
     * 返回：
     */
    public function afterClass(){
        $uid=intval($_POST["uid"]);
        $chnid=intval($_POST["chnid"]);
        $rec=D("exercise")->getAfterClass($chnid);
        if(null!=$rec){
            $webVar=$rec;
            $webVar['endtime']=strtotime($rec['etime'])-10; //截止时间前10秒交卷
            $webVar['etime']=substr($rec['etime'],5,11);
            //取用户的答案，不一定有
            $answer=D("answer")->where(array("exerciseid"=>$rec['id'],"userid"=>$uid))->getField("answer");
            if(null != $answer) $webVar["answer"]=htmlspecialchars(trim($answer));
            else $webVar["answer"]="";

//var_dump($webVar);
            $webVar["uid"]=$uid;
            $webVar["chnid"]=$chnid;
            $webVar["contextToken"]=session_id();
            $this->assign($webVar);
            $this->display("FE_exercise:afterClass");
        }else{
            echo "<div style='position: relative; top:30%; text-align: center;'>没找到练习题</div>";
        }

    }

    /**
     * 接收前端提交的答案
     */
    public function saveAnserJson(){
        //var_dump($_POST);
        try{
            if($_POST['contextToken'] !=session_id()) throw new Exception("非法访问");

            $exid=intval($_POST['exid']);
            $uid=intval($_POST["uid"]);
            $qt=intval($_POST["qt"]);
            if(1>$exid || 1>$uid || 1>$qt) throw new Exception("参数错误");

            if($qt>6) $qt=6;
            if(1==$qt) $answer=$_POST["A"];
            else{
                $answer="";
                for($i=65; $i<65+$qt; $i++){
                    $ch=chr($i);
                    if('on'==$_POST[$ch]) $answer .=$ch;
                }
            }
            $inser=array("exerciseid"=>$exid, "userid"=>$uid);
            $update=array("answer"=>$answer);
            $rt=insertOrUpdate("answer",$inser,$update);
            if(false===$rt) throw new Exception("数据保存失败");
            Oajax::successReturn();
        }catch (Exception $e){
            Oajax::errorReturn($e->getMessage());
        }
    }

    /**
     * 堂上练习与前端通讯接口，只能由BE_communicateAction内调用
     *
     * @param string $contextToken  调用者需要提供当前sessionid
     * @param array $commPara   通讯模块的公共通讯参数
     * @param array $params     本应用专有的通讯数据
     * @return mixed    null出错或本应用没有数据需要发送到前端，array-发送给前端的数据
     */
    public function communicate($contextToken,$commPara,$params){
        try{
            if($contextToken != session_id()) throw new Exception("非法调用");
            $chnid=intval($commPara["chnid"]);
            $retArr=array();
            //TODO:若前端提交答案处理之
            if(!empty($params)){
                $exid=intval($params['exid']);
                $uid=intval($params["uid"]);
                $qt=intval($params["qt"]);
                if(1>$exid || 1>$uid || 1>$qt) throw new Exception("参数错误");
                if($qt>6) $qt=6;
                if(1==$qt) $answer=$params["A"];
                else{
                    $answer="";
                    for($i=65; $i<65+$qt; $i++){
                        $ch=chr($i);
                        if('on'==$params[$ch]) $answer .=$ch;
                    }
                }
                $inser=array("exerciseid"=>$exid, "userid"=>$uid);
                $update=array("answer"=>$answer);
                $rt=insertOrUpdate("answer",$inser,$update);
                if(false===$rt) {
                    $retArr["saved"]=0;
                    $retArr["saveError"]="数据保存失败";
                }else{
                    $retArr["saved"]=1;
                }
            }
//var_dump($retArr);
            //若前端当前没显示练习窗口，检查有没题目要发送
            if($commPara["exercise"]["showing"]==1){
                $paper=D("exercise")->getInClass($chnid);
                if(!empty($paper)){
                    $paper["chnid"]=$chnid;
                    $paper["uid"]=$commPara["user"]["uid"];
                    if($paper["qt"]>6) $paper["qt"]=6;
                    $paper["contextToken"]=$contextToken;
                    //检查用户此题目是否已经作答
                    $isAnswer=D("answer")->where(array("exerciseid"=>$paper["id"],"userid"=>$paper["uid"]))->count();

                    if($isAnswer==0){
                        //未作答才显示题目
                        $this->assign($paper);
                        $retArr["paper"]=$this->fetch("FE_exercise:fetchPaper");
                    }
                }
                //echo D("exercise")->getLastSql();
                //var_dump($paper);
            }
            return $retArr;
        }catch (Exception $e){
            $retArr["error"]=$e->getMessage();
            return $retArr;
        }
    }

}