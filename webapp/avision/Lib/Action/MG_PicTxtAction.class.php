<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/11/7
 * Time: 20:43
 * 提供图文直播管理界面
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';
require_once LIB_PATH."Model/ChannelModel.php";
require_once LIB_PATH."Model/UserModel.php";

class MG_PicTxtAction extends AdminBaseAction{

    /**
     * 统一入口，避免授权困难，根据$func指示调用不同的功能函数
     * @param string $work
     */
    public function index($work="main"){
//var_dump($_REQUEST);
        switch ($work){
            /*
            case "main":    //显示主管理界面
                $this->main();
                break;
            case "chnList": //显示频道列表
                $this->chnList();
                break;
            case "listSearch":
                $this->listSearch();    //图文直播项列表页面
                break;
            */
            case "itemList":    //取图文列表项数据
                $this->itemListJson();
                break;
            case "getItem":     //取一条记录的详细数据
                $this->getItemJson();
                break;
            default:
                //若有work同名的方法则调用之
                if(method_exists ($this,$work)) $this->$work();
                else echo "不可识别的功能请求。";
                break;
        }
        return;
    }

    //主界面，显示频道列表查询条件
    private function main(){
        $this->baseAssign();
        $this->assign('mainTitle','频道图文直播后台');
        $webVar=array();
        $webVar['viewAll']=($this->isOpPermit('A'))?"true":"false"; //是否锁定owner

        $this->assign($webVar);
        $this->display("main");
    }

    //查询并显示符合条件的频道列表，ajax调用直接输出HTML
    private function chnList(){
        $webVar=array();
        $webVar['viewAll']=($this->isOpPermit('A'))?"true":"false";
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
        if(count($chnList)>0){
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
        $this->display("chnList");
    }

    /**
     * 查询并显示符合条件的图文项目，ajax调用直接输出html
     * 接受以下POST的查询条件：
     *  - chnId 频道ID
     *  - beginTime 图文直播项的发布日期范围，开始
     *  - endTime   结束
     */
    private function listSearch(){
        $condTpl=array("chnId"=>0, "beginTime"=>"","endTime"=>"");
        $cond=ouArrayReplace($condTpl,$_POST);  //从POST中接收查询条件
        $para=array();
//dump($cond);
        try{
            //检查查询条件
            if($cond['chnId']<1) throw new Exception("必须选择一个频道!");
            $cond["endTime"] =date('Y-m-d',strtotime('+1 day',strtotime($cond["endTime"])));
            if(false===strtotime($cond['beginTime']) || false===strtotime($cond["endTime"])) throw new Exception("日期时间格式错误。");

            //生成符合TP框架的条件格式
            $para=array("cond"=>array("chnid"=>$cond['chnId'], "publishtime"=>array("exp"," between '".$cond['beginTime']."' and '".$cond["endTime"]."') or (publishtime<'2000-01-01' ")));
//dump($para);
            //接受新的查询条件时确定命中记录数，分页查找时不另行计算
            $dbPictxt=D("pictxt");
            $totalRows=$dbPictxt->where($para['cond'])->count();
            $para['totalRows']=$totalRows;
//echo $dbPictxt->getLastSql();
//var_dump($totalRows);
        }catch (Exception $e){
            echo $e->getMessage();
            return;
        }
        condition::save($para,"pictxtListSearch");

        //新建条目时需要知道当前选择的频道、节目，传到前端记录。
        $webVar=array("chnid"=>$_POST['chnId']);
        $webVar['programid']=empty($_POST['programid'])?0:intval($_POST['programid']);
        $this->assign($webVar);
        $this->display("listSearch");
    }

    /**
     * 符合datagrid数据格式的，取图文列表数据
     *  在session变量中已经包含查询条件及命中记录数
     */
    private function itemListJson($page=1,$rows=20){
        //从session变量中读取查询条件及行数
        $para=condition::get("pictxtListSearch");
//var_dump($para);
        $result=array();
        try{
            if(empty($para) || empty($para['cond']) || !isset($para['totalRows'])) throw new Exception('缺少处理参数');
            if(empty($para)) throw new Exception("没有符合条件的记录。");
            $dbPictxt=D("pictxt");
            $rows= $dbPictxt->where($para['cond'])->field("id,chnid,programid,title,publishtime")->order('publishorder')->page($page,$rows)->select();
//echo $dbPictxt->getLastSql();
            if(null==$rows) throw new Exception("数据库访问异常。");
            $result['rows']=$rows;
            $result['total']=$para['totalRows'];
        }catch (Exception $e){
            $result=array("total"=>0, "rows"=>array(), "msg"=>$e->getMessage());
        }
        if(null==$result)	echo '[]';
        else echo json_encode2($result);
    }

    /**
     * 以JSON格式返回一条图文直播项的数据，提供给编辑界面
     */
    private function getItemJson(){
//dump($_POST);
        $webVar=array();
        $dbPic=D("pictxt");
        if($_POST["isNewRecord"]=="true"){
            //新增记录
            $webVar['title']="新标题".date("Y-m-d");
            $webVar['publishtime']='1000-01-01';
            $webVar['html']=' ';
            $webVar['id']=$dbPic->add($webVar);
        }else{

            $webVar['title']=$_POST['title'];
            $webVar['publishtime']=$_POST['publishtime'];
            $webVar['id']=intval($_POST['id']);
            $webVar['chnid']=intval($_POST['chnid']);
            $webVar['programid']=intval($_POST['programid']);
            $webVar['html']=$dbPic->where("id=".$webVar['id'])->getField('html');
        }
        Oajax::successReturn($webVar);
    }

    //发布指定的条目
    private function publish(){
        $id=intval($_POST['id']);
        try{
            if(empty($id)) throw new Exception("缺少记录ID");
            $dbPic=D("pictxt");
            $date=date("Y-m-d H:i:s");
            $maxOrder=$dbPic->max("publishorder");  //同一频道同时发布的机会很低，取了相同值的机会不大
            if(false===$maxOrder) throw new Exception("数据库错误");

            $rt=$dbPic->where("id=".$id)->save(array('publishtime'=>$date, 'publishorder'=>$maxOrder+1));
//echo $dbPic->getLastSql();
            if(false===$rt) throw new Exception("发布失败");
            Oajax::successReturn(array("msg"=>"发布成功","publishtime"=>$date,'publishorder'=>$maxOrder+1));
        }catch (Exception $e){
            Oajax::errorReturn($e->getMessage());
        }

    }

    /**
     * 建立新的条目或，更新指定的条目，只会更新未发布的条目
     * 未定义id,或id=0新建记录
     * 只会更新html，title字段
     */
    private function saveItem(){
        $_POST['id']=intval($_POST['id']);
        $_POST['programid']=intval($_POST['programid']);

        $dbPic=D("pictxt");
        $rec=array("title"=>(empty($_POST["title"]))?"":$_POST["title"],
            "html"=>(empty($_POST["html"]))?"":$_POST["html"]
            );
        $webVar=$rec;
        try{
            if(empty($_POST['id'])){
                //新建记录
                if(empty($_POST['chnid'])) throw new Exception("必须指定频道。");

                $webVar['chnid']=$rec['chnid']=$_POST['chnid'];
                $webVar['programid']=$rec['programid']=(empty($_POST['programid']))?0:$_POST['programid'];
                $webVar['publishtime']=$rec['publishtime']=(empty($_POST['publishtime']))?"1000-01-01":$_POST['publishtime'];
                $webVar['publishorder']=$rec['publishorder']=0;
                $rt=$dbPic->add($rec);
                if(null==$rt) throw new Exception("新增失败！");
                $webVar['id']=$rt;
                $webVar['isNewRecord']=true;
            }else{
                //更新记录
                if(empty($_POST['id'])) throw new Exception("缺少条目ID");
                $webVar['id']=$_POST['id'];
                $rt=$dbPic->where("id=".$_POST['id'])->save($rec);
                if(false===$rt) throw new Exception("更新失败");
            }
        }catch (Exception $e){
            Oajax::errorReturn($e->getMessage());
        }
        Oajax::successReturn($webVar);
    }

    /**
     * 删除指定的条目
     */
    protected function destroy(){
        $webVar=array();
        $dbPic=D("pictxt");
        $_POST['id']=intval($_POST['id']);
        //$_POST['id']=0;
        try{
            if(empty($_POST['id'])) throw new Exception("缺少参数ID");
            //TODO：先扫描HTML，删除上传的图片文件

            $rt=$dbPic->where("id=".$_POST['id'])->delete();
            if($rt===false) throw new Exception("删除失败");
        }catch (Exception $e){
            $webVar['isError']=true;
            $webVar['msg']=$e->getMessage();
            Oajax::errorReturn("",$webVar);
        }
        Oajax::successReturn();
    }

    /**
     * 撤回已发布的条目
     */
    protected function recallItem(){
        $webVar=array();
        $dbPic=D("pictxt");
        $_POST['id']=intval($_POST['id']);
        try{
            if(empty($_POST['id'])) throw new Exception("缺少参数ID");

            $rt=$dbPic->where("id=".$_POST['id'])->save(array("publishtime"=>"1900-01-01"));
            if($rt===false) throw new Exception("撤回失败");
            $webVar['publishtime']="1900-01-01";
        }catch (Exception $e){
            $webVar['isError']=true;
            $webVar['msg']=$e->getMessage();
            Oajax::errorReturn("",$webVar);
        }
        Oajax::successReturn($webVar);
    }
    /**
     * 处理上传文件
     * POST参数：imgFile: 文件form名称，dir: 上传类型，分别为image、flash、media、file
     * URL传入参数：chnid:频道ID, programid：节目ID
     *
     * 返回格式（JSON）
     * //成功时
    {
    "error" : 0,
    "url" : "http://www.example.com/path/to/file.ext"
    }
    //失败时
    {
    "error" : 1,
    "message" : "错误信息"
    }
     */
    protected function editorUploadJson(){
        $chnid=$_REQUEST['chnid'];
        $programid=$_REQUEST['programid'];
        //var_dump($_REQUEST);
        //定义允许上传的类型及文件扩展名
        $ext_arr = array(
            'image' => array('gif', 'jpg', 'jpeg', 'png', 'bmp'),
            'flash' => array('swf', 'flv'),
            'media' => array('swf', 'flv', 'mp3', 'wav', 'wma', 'wmv', 'mid', 'avi', 'mpg', 'asf', 'rm', 'rmvb'),
            'file' => array('doc', 'docx', 'xls', 'xlsx', 'ppt', 'htm', 'html', 'txt', 'zip', 'rar', 'gz', 'bz2'),
        );
        $returnData=array();
        try{
            if(1>$chnid) throw new Exception('必须选定频道!');
            $fileType = empty($_['dir']) ? 'image' : trim($_POST['dir']);	//读取前端提供的文件类型
            if (empty($ext_arr[$fileType])) throw new Exception('不支持此文件类型：'.$fileType);

            $orgFileName=$_FILES['imgFile']['name'];	//源文件名
            $fileExt = pathinfo($orgFileName, PATHINFO_EXTENSION);
            //检查扩展名
            if (in_array(strtolower($fileExt), $ext_arr[$fileType]) === false)
                throw new Exception("上传文件扩展名是不允许的扩展名。\n只允许" . implode(",", $ext_arr[$fileType]) . "格式。");

            $dbChannel=D('channel');
            //计算并建立存储路径
            $webroot=$_SERVER['DOCUMENT_ROOT'];
            $urlpath=$dbChannel->imgFilePath($chnid,'u',true)."/pictxt";
            $physicalPath=$webroot.$urlpath;
            if(!is_dir($physicalPath)) {
                if (!mkdir($physicalPath, 0774, true)) throw new Exception('目录创建失败');
            }
            $urlpath .='/';
            $targetFile=uniqid("pic",true).".".$fileExt;
            $ret = move_uploaded_file($_FILES['imgFile']['tmp_name'], $webroot.$urlpath.$targetFile);
            if(!$ret) throw new Exception('文件写入失败。');

            //TODO:要集中记录上传的文件名，否则文件会无法删除。

            $returnData['url']=$urlpath.$targetFile;
            $returnData['error']=0;

        }catch (Exception $e){
            $returnData['error']=1;
            $returnData['message']=$e->getMessage();
        }
        Oajax::ajaxReturn($returnData);
    }
}