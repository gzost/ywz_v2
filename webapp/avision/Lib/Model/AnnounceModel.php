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
            if(is_array($attr))  $recs[$key]=array_merge($row,$attr);
            unset($recs[$key]["attr"]);
        }
        //echo $this->getLastSql();
        //dump($recs);
        return $recs;
    }
}