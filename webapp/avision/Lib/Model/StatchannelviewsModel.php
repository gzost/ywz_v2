<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/4/28
 * Time: 17:18
 */

class StatchannelviewsModel extends Model
{
    /**
     * 插入或更新记录。
     * 若已存在 chnid,userid,rq 相同的记录，累加duration，否则插入记录。
     * @param $rec
     * @return int  1-记录已更新，其它int新记录的记录ID，false-插入错误
     */
    public function inserUpdate($rec){
        if($rec['chnid']<1 || $rec["userid"]<1 || $rec["rq"]<"2000-01-01" || $rec["duration"]==0 ) return 0;    //数据不完整不插入也不更新
        //先尝试更新
        $cond=array("chnid"=>$rec['chnid'], "userid"=>$rec["userid"], "rq"=>$rec["rq"]);
        $dur=intval($rec["duration"]);
        $rt=$this->where($cond)->setInc("duration",$dur);
//var_dump($rt);
        if(0==$rt){ //没记录更新说明原来记录不存在，插入
            $cond['duration']=$rec["duration"];
            $rt=$this->add($cond);
        }
        unset($cond,$dur,$sql);
        return $rt;
    }
}
?>