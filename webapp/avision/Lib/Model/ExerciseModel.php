<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/4/25
 * Time: 22:23
 */

class ExerciseModel extends Model{

    /**
     * 取指定频道的课后练习题，读取规则：已经发布及已经发卷的题目
     * 若有多条记录符合条件，取最新（id最大）的一条
     * @param int $chnid    频道
     * @param int $vodid
     * @return mixed    成功返回exercise记录并附加对应题目内容block字段的数组，找不到记录返回NULL
     */
    public function getAfterClass($chnid,$vodid=0){
        $nowStr=date("Y-m-d H:i:s");    //当前时间串
        $cond=array("chnid"=>$chnid, "vodid"=>$vodid, "status"=>1, "type"=>1, "btime"=>array("ELT",$nowStr));
        $row=$this->where($cond)->order("id desc")->find();
        if(is_array($row)){
            //找到记录
            $block=D("block")->field("color,bgrcolor,html")->where("id=".$row["content"])->find();
            if(is_array($block)){
                foreach ($block as $k=>$v) $row[$k]=$v;
            }
            return $row;
        }else{
            return null;
        }
    }
}