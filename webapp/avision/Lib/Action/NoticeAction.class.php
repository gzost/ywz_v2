<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/3/30
 * Time: 16:54
 * 系统通知管理及显示功能
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once APP_PATH.'../public/Ou.Function.php';

class NoticeAction extends AdminBaseAction{

    /**
     * 统一入口，避免授权困难，根据$func指示调用不同的功能函数
     * @param string $work  默认'main'
     */
    public function index($work='main'){
        //若有work同名的方法则调用之
        if(method_exists ($this,$work)) $this->$work();
        else echo "不可识别的功能请求。";
        return;
    }

    private function main(){
        $this->baseAssign();
        $this->assign('mainTitle','系统公告');

        $rec=D("notice")->find(1);
        $webVar=$rec;

        $this->assign($webVar);
        $this->display("main");
    }

    private function saveJson(){
        $recordTpl=array("headline"=>"", "content"=>"", "begintime"=>"", "endtime"=>"");
        $record=ouArrayReplace($recordTpl,$_POST);
        $rt=D("notice")->where("id=1")->save($record);
        if(false===$rt) Oajax::errorReturn("公告更新失败。");
        else Oajax::successReturn();
    }
}