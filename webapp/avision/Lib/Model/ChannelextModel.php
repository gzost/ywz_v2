<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/4/4
 * Time: 15:50
 *
 * 频道封面
 */
require_once(LIB_PATH.'Model/ChannelModel.php');

class ChannelextModel extends Model{

    /**
     * 频道封面HTML
     * @param int $chnid
     * @param  string $showcover    显示状态条件，不提供为忽略
     * @return mixed null-没有需要显示的封面或者禁止显示封面，string-频道封面的HTML
     */
    public function getCoverHtml($chnid=0, $showcover=""){
        try{
            if(1>$chnid) throw new Exception("参数错误");
            $cond=array("chnid"=>$chnid);
            if(""!==$showcover) $cond["showcover"]=$showcover;
            $rec=$this->field("coverhtml,coverbackground,covercolor,showcover")->where($cond)->find();
            //var_dump($rec); echo $this->getLastSql();
            if(empty($rec)) throw new Exception("没有可显示的封面");
            return $rec;
        }catch (Exception $e){
            return null;
        }
    }

    /**
     * 新增或更新封面信息
     * @param  int $chnid   频道ID
     * @param arrar $record 其它记录字段信息，包括：coverhtml,coverbackground,showcover
     * @throws Exception
     */
    public function saveCover($chnid,$record){
        $coverhtml=(null==$record["coverhtml"])?"":$record["coverhtml"];
        $coverbackground=(null==$record["coverbackground"])?"#046adb":$record["coverbackground"];
        $covercolor=(null==$record["covercolor"])?"#eeeef0":$record["covercolor"];
        $showcover=(1==$record["showcover"])?1:0;
        $query="insert into ".C("DB_PREFIX")."channelext(chnid,coverhtml,coverbackground,covercolor,showcover) ".
            " value($chnid,'$coverhtml','$coverbackground','$covercolor',$showcover) ".
            " on duplicate key update coverhtml='$coverhtml', coverbackground='$coverbackground', covercolor='$covercolor',showcover=$showcover ";
        $rt=$this->execute($query);
        if(false===$rt) throw new Exception($this->getLastSql());

        ////删除cover目录中不在html图片连接中的图片文件
        //封面中包含的文件
        $pattern = '/<img.*?src="(.*?)".*?\/?>/i';
        preg_match_all($pattern,$coverhtml,$match);
        $coverFile=array();
        foreach ($match[1] as $path){
            $coverFile[]= basename($path);
        }
        //目录中包含的文件
        $imgPath=D("channel")->imgFilePath($chnid,'p',false)."/cover";
        if(!chdir($imgPath)) throw new Exception("无法访问图片目录");
        $dirFile = array();
        $data = scandir('.');
        foreach ($data as $value){
            if($value != '.' && $value != '..'){
                $dirFile[] = iconv("gbk","utf-8",$value);
            }
        }
        //差集
        $diff=array_diff($dirFile,$coverFile);

        //删除差集
        foreach ($diff as $file){
            $file=iconv("utf-8","gbk",$file);
            unlink($file);
        }
    }
}