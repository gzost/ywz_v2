<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/12/4
 * Time: 20:07
 */
require_once APP_PATH.'../public/Ou.Function.php';
class AnnounceModel extends Model
{
    /**
     * 取可显示记录
     */
    public function getShowItem($agentid=0,$ownerid=0,$chnid=0){
        $fields="zone,type,content,attr";
        $now=date("Y-m-d H:i:s");   //取当前时间
        $cond=array("btime"=>array("ELT",$now),"etime"=>array("EGT",$now));
        $cond["agentid"]=(empty($agentid))?0:array($agentid,0,'or');
        $cond["ownerid"]=(empty($ownerid))?0:array($ownerid,0,'or');
        $cond["chnid"]=(empty($chnid))?0:array($chnid,0,'or');
        //dump($cond);
        $recs=$this->field($fields)->where($cond)->select();
//dump($recs);
        foreach ($recs as $key=>$row){
            $attr=json_decode($row["attr"],true);
            if(is_array($attr)){
                if(!empty($attr['imgurl'])){
                    $attr['imgurl']=$this->getImgUrlPrefix().$attr['imgurl'];
                }
                $recs[$key]=array_merge($row,$attr);
            }
            unset($recs[$key]["attr"]);
        }
        //echo $this->getLastSql();
        //dump($recs);
        return $recs;
    }

    /**
     * 取消息图片存放URL路径的固定部分，默认是/files/announce
     * 其中：files可由userFileBasePath配置项定义
     * @return string   URL路径的固定部分
     */
    public function getImgUrlPrefix(){
        $base=(empty(C("userFileBasePath")))? "/files": C("userFileBasePath");
        return $base.'/announce';
    }

    /**
     * 返回根据id确定的子路径/123/456/789
     * @param $id
     * @param bool $create    如果是true,会检查目录若不存在则建立目录
     * @return string
     * @throws Exception    无法建立目录时抛出错误
     */
    public function getImgUrlPath($id,$create=false){
        $path=sprintf("/%03d/%03d/%03d",floor($id/1000000),floor($id/1000),$id%1000);
        if($create){
            $fullPath=$_SERVER['DOCUMENT_ROOT'].$this->getImgUrlPrefix().$path;
            if(!is_dir($fullPath)) {
                $rt=mkdir($fullPath,0755,true);
                if(!$rt) throw new Exception("无法建立目录！");
            }
        }
        return $path;
    }

    /**
     * 生成唯一的图片文件名
     */
    public function genImgFileName($type=''){
        $ext='';
        switch ($type){
            case 'image/jpeg':
                $ext='.jpg';
                break;
            case 'image/gif':
                $ext='.gif';
                break;
            case 'image/png':
                $ext='.png';
                break;
        }
        return 'AN'.base_convert(uniqid(),16,36).$ext;
    }

    //删除指定id的图片文件
    public function removeImgFile($id){
        $attr=$this->where("id=".$id)->getField('attr');
        $attrArr=json_decode($attr,true);
        if(!empty($attrArr['imgurl'])){
            $fullPath=$_SERVER['DOCUMENT_ROOT'].$this->getImgUrlPrefix().$attrArr['imgurl'];
            unlink($fullPath);
        }
    }
}