<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/4/22
 * Time: 22:25
 *
 * 可编辑块操作部件
 */
require_once APP_PATH.'../public/Ou.Function.php';
require_once(LIB_PATH.'Model/BlockModel.php');

class MD_BlockAction extends Action {
    function __construct() {
        parent::__construct();
        session_start();
    }

    /**
     * 取记录
     * @param int $id   记录ID
     * @param string $contextToken  当前session_id
     * @return mixed    记录数组，找不到记录返回null
     */
    public function getRec($id,$contextToken){
        if($contextToken != session_id()) return null;
        return D("block")->where("id=".$id)->find();
    }

    /**
     * 以JSON格式返回块记录
     * 通过POST传递：id,contextToken
     */
    public function getRecJson(){
        $rec=$this->getRec($_POST['id'],$_POST['contextToken']);
        if(is_array($rec)) echo json_encode2($rec);
        else echo "{}";
    }

    /**
     * kindeditor文件上传后端
     */
    public function editorUploadJson(){
        //var_dump($_POST);
        $blockid=intval($_POST["blockid"]);
        //定义允许上传的类型及文件扩展名
        $ext_arr = array(
            'image' => array('gif', 'jpg', 'jpeg', 'png', 'bmp'),
            'flash' => array('swf', 'flv'),
            'media' => array('swf', 'flv', 'mp3', 'wav', 'wma', 'wmv', 'mid', 'avi', 'mpg', 'asf', 'rm', 'rmvb'),
            'file' => array('doc', 'docx', 'xls', 'xlsx', 'ppt', 'htm', 'html', 'txt', 'zip', 'rar', 'gz', 'bz2'),
        );
        $returnData=array();
        try{
            if($_POST["contextToken"] != session_id()) throw new Exception("非法访问");
            if(1>$blockid) throw new Exception('缺少blockid!');
            $fileType = empty($_['dir']) ? 'image' : trim($_POST['dir']);	//读取前端提供的文件类型
            if (empty($ext_arr[$fileType])) throw new Exception('不支持此文件类型：'.$fileType);

            $orgFileName=$_FILES['imgFile']['name'];	//源文件名
            $fileExt = pathinfo($orgFileName, PATHINFO_EXTENSION);
            //检查扩展名
            if (in_array(strtolower($fileExt), $ext_arr[$fileType]) === false)
                throw new Exception("上传文件扩展名是不允许的扩展名。\n只允许" . implode(",", $ext_arr[$fileType]) . "格式。");

            $dbBlock=D('block');
            //计算并建立存储路径
            $webroot=$_SERVER['DOCUMENT_ROOT'];
            $urlpath=$dbBlock->getPath($blockid,true);
            $urlpath .='/';
            $targetFile='BL'.Ouuid().".".$fileExt;
            $ret = move_uploaded_file($_FILES['imgFile']['tmp_name'], $webroot.$urlpath.$targetFile);
            if(!$ret) throw new Exception('文件写入失败。');

            $returnData['url']=$urlpath.$targetFile;
            $returnData['error']=0;

        }catch (Exception $e){
            $returnData['error']=1;
            $returnData['message']=$e->getMessage();
        }
        Oajax::ajaxReturn($returnData);
    }
}