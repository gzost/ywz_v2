<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/4/3
 * Time: 16:19
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once(LIB_PATH.'Model/ChannelextModel.php');
require_once(LIB_PATH.'Model/AgentModel.php');
require_once(LIB_PATH.'Model/UserModel.php');
require_once(LIB_PATH.'Model/ChannelModel.php');

class MG_channelcoverAction extends AdminBaseAction{
    private $params;
    //{"operation":[{"text":"管理自己频道","val":"R"},{"text":"管理所有频道","val":"A"},{"text":"管理所在机构频道","val":"G"}]}
    public function main(){
        $paramsTpl=array("MGcoverToken"=>"","chnid"=>0,"chnName"=>"","func"=>"init");
        $this->params=ouArrayReplace($paramsTpl,$_POST);
        $this->params['uid']=$this->userId();
        $this->params["agent"]=$this->getUserInfo("agent"); //当前用户所在机构
        $this->params["rightA"]=$this->isOpPermit("A"); //可管理所有频道[true|false]
        $this->params["rigntG"]=$this->isOpPermit("G"); //可管理机构频道
        switch ($this->params["func"]){
            case "init":
                $this->init();
                break;
            case "getCoverJson":
                $this->getCoverJson();
                break;
            case "showfilter":
                $this->showfilter();
                break;
            default:
                $func=$this->params["func"];
                $this->$func();
                break;
        }
    }

    /**
     * 初始化显示界面
     */
    private function init(){
        $this->baseAssign();
        $webVar=$this->params;
        $this->assign($webVar);
        $this->display("MG_channelcover:init");
    }

    //取指定频道的封面信息
    private function getCoverJson(){
        $chnid=intval($_POST["chnid"]);
        $webVar=array();
        //显示封面信息部分
        $rec=D("Channelext")->getCoverHtml($chnid);
        //var_dump($rec);
        $webVar["showcover"]=$rec["showcover"];
        $webVar["noclose"]=$rec["noclose"];
        $webVar["coverhtml"]=(empty($rec["coverhtml"]))?"":$rec["coverhtml"];
        $webVar["coverbackground"]=(empty($rec["coverbackground"]))?"#046adb":$rec["coverbackground"];
        $webVar["covercolor"]=(empty($rec["covercolor"]))?"#eeeef0":$rec["covercolor"];
        Oajax::successReturn($webVar);
    }

    /**
     * 显示频道过滤条件
     */
    private function showfilter(){
        $filterTpl=array('agent'=>$this->params["agent"], 'owner'=>$this->getUserInfo('account'), 'chnName'=>'', "container"=>"window");
        $webVar=ouArrayReplace($filterTpl,$_POST,"org");

        //取机构列表
        $dbAgent=D("agent");
        $agentList=$dbAgent->getNameList();
        $empItem=array('id'=>0, 'name'=>' ');
        if(is_array($agentList)) array_unshift($agentList,$empItem);
        else $agentList=array($empItem);
        $webVar["agentReadonly"]=($this->params["rightA"])?"false":"true";


        $agentListJson=str_replace('"',"'",json_encode2($agentList));
//var_dump($agentListJson);
        $webVar['agentListJson']=$agentListJson;

        //设置频道属主ID
        if(!empty($webVar['owner'])){
            $dbUser=D('user');
            $webVar['ownerid']=$dbUser->getUserId($webVar['owner']);
        }else $webVar['ownerid']=0;
        $webVar["ownerReadonly"]=($this->params["rightA"]||$this->params["rightG"])?"false":"true";
        $this->assign($webVar);
        $this->display("MG_channelcover:showfilter");
    }

    /**
     * 根据查询条件输出频道符合combobox数据要求的频道列表
     */
    private function getChnListJson(){
        $filterTpl=array('agent'=>$this->params["agent"], 'owner'=>$this->getUserInfo('account'), 'chnName'=>'');
        $webVar=ouArrayReplace($filterTpl,$_POST,"org");

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

    private function saveJson(){
        //保存封面信息部分
        $paramsTpl=array("chnid"=>0, "coverbackground"=>"#046adb", "coverhtml"=>"","covercolor"=>"#eeeef0",
            "showcover"=>0,"noclose"=>0);
        $params=ouArrayReplace($paramsTpl,$_POST);
        if(!empty($params["showcover"])) $params["showcover"]=1;
        if(!empty($params["noclose"])) $params["noclose"]=1;
        try{
            $chnid=intval($params["chnid"]);
            if($chnid<1) throw new Exception("缺少参数chnid");
            $rt=D("Channelext")->saveCover($chnid,$params);
            Oajax::successReturn();
        }catch (Exception $e){
            Oajax::errorReturn($e->getMessage());
        }
    }

    private function editorUploadJson(){
        //var_dump($_POST);
        $chnid=intval($_POST["chnid"]);
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
            $urlpath=$dbChannel->imgFilePath($chnid,'u',true)."/cover";
            $physicalPath=$webroot.$urlpath;
            if(!is_dir($physicalPath)) {
                if (!mkdir($physicalPath, 0774, true)) throw new Exception('目录创建失败');
            }
            $urlpath .='/';
            //$targetFile=uniqid("cv",true).".".$fileExt;
            $targetFile='cv'.Ouuid().".".$fileExt;
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