<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/4/21
 * Time: 17:04
 * 学习管理：试卷编辑
 */

require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once(LIB_PATH.'Model/BlockModel.php');

class MG_learningAction extends AdminBaseAction{
    private $params=array();
    const DEF_COLOR="#333333";      //默认练习卷文字颜色
    const DEF_BGRCOLOR="#FFFFFF";   //默认练习卷背景颜色

    public function t(){
        $t=array();
        $a=$t[1];
        var_dump($t,is_array($t));
    }
    public function main()  {
        $paramsTpl = array( "chnid" => 0, "chnName" => "", "func" => "init");
        $this->params = ouArrayReplace($paramsTpl, $_POST);
        $this->params['uid'] = $this->userId();
        $this->params["agent"] = $this->getUserInfo("agent"); //当前用户所在机构
        $this->params["rightA"] = $this->isOpPermit("A"); //可管理所有频道[true|false]
        $this->params["rightG"] = $this->isOpPermit("G"); //可管理机构频道
        $func=$this->params["func"];
        $this->$func();
    }

    /**
     * 输出练习编辑主界面
     */
    private function init(){
        $this->baseAssign();
        $webVar=$this->params;
        $webVar["right"]["A"]=$this->isOpPermit("A");
        $webVar["right"]["G"]=$this->isOpPermit("G");
        $webVar["account"]=$this->getUserInfo('account');   //当前用户的账号
        $webVar["contextToken"]=session_id();   //上下文标识

        $this->assign($webVar);
        $this->display("MG_learning:init");
    }

    /**
     * 取指定频道的试卷列表
     * 返回符合datagrid格式的json对象
     */
    private function getPaperListJson(){
        $paramsTpl = array( "chnid" => 0, "chnName" => "", "contextToken" => "","page"=>1, "rows"=>20);
        $para=ouArrayReplace($paramsTpl, $_POST);
        try{
            if($para["contextToken"] != session_id()) throw new Exception("参数错误");
            $dbExercise=D("exercise");
            $cond=array("chnid"=>$para["chnid"]);
            $totalRows=$dbExercise->where($cond)->count();
            if(1>$totalRows) throw new Exception("no data");
            $records=$dbExercise->where($cond)->page($para["page"],$para["rows"])->select();
            $result=array("rows"=>$records, "total"=>$totalRows);
            echo json_encode2($result);
        }catch (Exception $e){
            echo "[]";
        }
    }

    /**
     * 新增练习卷，以Json格式输出新增的记录，以及成功失败标志
     * POST传入：chnid,vodid
     */
    private function addPaperJson(){
        $dbBlock=D("block");
        $dbExercise=D("exercise");
        try{
            $chnid=intval($_POST["chnid"]);
            $vodid=intval($_POST["vodid"]);
            if($_POST["contextToken"] != session_id() || 1>$chnid) throw new Exception("参数错误");
            $dbBlock->startTrans();
            $newRec=array("color"=>self::DEF_COLOR, "bgrcolor"=>self::DEF_BGRCOLOR, "html"=>"");    //默认新建的block记录
            $blockid=$dbBlock->add($newRec);
            if(null==$blockid) throw new Exception("建立block记录失败:".$dbBlock->getLastSql());

            $newRec=array("chnid"=>$chnid, "vodid"=>$vodid, "content"=>$blockid, "type"=>1, "status"=>0, "answer"=>"","qt"=>4);
            $newRec["btime"]=date("Y-m-d H:i");
            $newRec["etime"]=date("Y-m-d H:i",strtotime("+1 day"));
            $newRec["title"]="新练习".$newRec["btime"];
            $exid=$dbExercise->add($newRec);
            if(null==$exid) throw new Exception("建立exercise记录失败:".$dbExercise->getLastSql());

            $newRec["id"]=$exid;
            $dbBlock->commit();
            Oajax::successReturn($newRec);
        }catch (Exception $e){
            $dbBlock->rollback();
            Oajax::errorReturn($e->getMessage());
        }
    }

    /**
     * 更新练习题
     * POST传入练习题记录的信息
     * 输出Jsonc成功：{success:"true", row:{exercise记录}}    失败：{success:"false", msg:"错误信息"}
     */
    private function savePaperJson(){
        $dbBlock=D("block");
        $dbExercise=D("exercise");
        //var_dump($_POST);
        try{
            if($_POST["contextToken"] != session_id()) throw new Exception("非法访问");
            $exid=intval($_POST["id"]);
            $blockid=intval($_POST["content"]);
            $exerciseTpl=array( "chnid"=>0, "vodid"=>0, "title"=>"", "content"=>$blockid, "btime"=>"2000-01-01", "etime"=>"2000-01-01", "type"=>1, "answer"=>"", "qt"=>4);   //练习记录模板
            $exercise=ouArrayReplace($exerciseTpl,$_POST);
            $exercise["status"]=($_POST["status"]=="on")?1:0;
            $blockTpl=array("color"=>self::DEF_COLOR, "bgrcolor"=>self::DEF_BGRCOLOR, "html"=>"");
            $block=ouArrayReplace($blockTpl,$_POST);

            if(1>$exid || 1>$blockid) throw new Exception("参数错误");
            $dbBlock->startTrans();
            $rt=$dbExercise->where("id=".$exid)->save($exercise);
            if(false===$rt) throw new Exception("更新失败".$dbBlock->getLastSql());

            $dbBlock->update($blockid,$block);  //更新练习题内容，同时删除不使用的上传文件
            $dbBlock->commit();
            $exercise['id']=$exid;
            Oajax::successReturn($exercise);
        }catch (Exception $e){
            $dbBlock->rollback();
            Oajax::errorReturn($e->getMessage());
        }
    }

    /**
     * 删除指定的练习
     * POST参数：contextToken, exit:"要删除的练习ID", content:"练习内容记录ID"
     */
    private function delPaperJson(){
        $dbBlock=D("block");
        $dbExercise=D("exercise");
        $dbAnswer=D("answer");
        //var_dump($_POST);
        try{
            if($_POST["contextToken"] != session_id()) throw new Exception("非法访问");
            $exid=intval($_POST["exid"]);
            $content=intval($_POST["content"]);
            if(1>$exid || 1>$content ) throw new Exception("参数错误。");

            $dbBlock->startTrans();
            //删除练习卷内容及相关文件
            $dbBlock->remove($content);

            //删除练习题相关的学员答案
            $rt=$dbAnswer->where("exerciseid=".$exid)->delete();
            if(false===$rt) throw new Exception("删除失败".$dbBlock->getLastSql());

            //删除练习题记录
            $rt=$dbExercise->where("id=".$exid)->delete();
            if(false===$rt) throw new Exception("删除失败".$dbBlock->getLastSql());

            $dbBlock->commit();
            Oajax::successReturn();
        }catch (Exception $e){
            $dbBlock->rollback();
            Oajax::errorReturn($e->getMessage());
        }
    }
}