<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2018-5-17
 * Time: 20:59
 *
 * 抽奖表数据模型
 */


class LotteryModel extends Model
{
    public function getListInChn($chnid){
        $cond=array('channelid'=>array($chnid,0,'OR'));
        $ret=$this->field('id,title,type,enroll_b,enroll_e,draw')->where($cond)->select();
        return $ret;
    }
}