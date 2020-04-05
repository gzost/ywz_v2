<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/4/4
 * Time: 15:50
 *
 * 系统公告
 */

class NoticeModel extends Model{

    /**
     * 取系统公告
     * @return mix null没有需要显示的公告，array-公告记录
     */
    public function getSystemNotice(){
        $date=date("Y-m-d H:i:s");  //当前时间
        try{
            $rec=$this->find(1);
            if(!is_array($rec)) throw new Exception("没有记录");
            if($rec["begintime"]>$date || $rec["endtime"]<$date) throw new Exception("不在公告显示时间范围");
            return $rec;
        }catch (Exception $e){
            return null;
        }
    }
}