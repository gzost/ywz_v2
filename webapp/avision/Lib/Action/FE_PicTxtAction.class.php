<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/11/15
 * Time: 21:10
 * 图文直播用户端界面
 */
require_once APP_PATH.'../public/SafeAction.Class.php';
class FE_PicTxtAction extends SafeAction{
    function __construct(){
        parent::__construct(2); //不考虑超时
    }

    public function index(){
        echo "index";
    }

    public function init(){
        $webVar=array();
        try{
            if(empty($_REQUEST['chnid'])) throw new Exception("初始化失败，刷新页面后再试。");
            $webVar['chnid']=$_REQUEST['chnid'];
            $webVar['programid']=(empty($_REQUEST['programid']))?'0':$_REQUEST['programid'];
            $webVar['loadDataUrl']=U("loadItem");
        }catch (Exception $e){
            echo $e->getMessage();
        }

        $this->assign($webVar);
        $this->display("init");
    }

    public function loadItem(){
        $webVar=array();
        $chnid=intval($_POST['chnid']);
        $direction=$_POST['direction'];
        $lastItem=intval($_POST["lastItem"]);
        $firstItem=intval($_POST["firstItem"]);

        $maxItems=10;   //每次最多输出条目数
        try{
            if(empty($chnid)) throw new Exception("必须选择频道。");
            if(empty($direction)) throw new Exception("缺少参数。");

            $cond=array("chnid"=>$chnid, "publishtime"=>array("GT","2000-00-00"));
            $order='desc';    //默认查询逆序输出
            if($direction=='after'){
                //读lastItem之后的记录
                if(!empty($lastItem)) {
                    $cond['publishorder']=array("GT",$lastItem);
                    $order='asc';
                }   //当$lastItem没定义或为空，是初始化的第一次输出，读最新的记录
            }else{
                //读firstItem之前的记录
                if(!empty($firstItem)) $cond['publishorder']=array("LT",$firstItem);
                else throw new Exception("延迟执行");
            }
            $dbPic=D('pictxt');
            $records=$dbPic->where($cond)->order("publishorder ".$order)->limit($maxItems)->select();
            if(null==$records) throw new Exception("没有符合条件的数据");
            $rows=count($records);  //命中记录数
            if('desc'==$order){
                $webVar['lastItem']=$records[0]['publishorder'];
                $records=array_reverse($records); //反转为publishorder顺序
                $webVar['firstItem']=$records[0]['publishorder'];
            }else{
                $webVar['firstItem']=$records[0]['publishorder'];
                $webVar['lastItem']=$records[$rows-1]['publishorder'];
            }
            //渲染条目，获得HTML
            $this->assign("rows",$records);
            $webVar['html']=$this->fetch("FE_PicTxt:loadItem");

        }catch (Exception $e){
            Oajax::errorReturn($e->getMessage());
        }
        Oajax::successReturn( $webVar);
    }
}