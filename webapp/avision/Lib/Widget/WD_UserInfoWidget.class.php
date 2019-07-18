<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/7/17
 * Time: 20:46
 * 用户信息显示控件
 */

class WD_UserInfoWidget extends Action
{
    public function baseInfo($uid){
        try{
            if($uid==0 || $uid==C('anonymousUserId') ) throw new Exception('您还未登录！');
            $webVar=array();
            //取用户基本信息
            $dalUser=D('user');
            $user=$dalUser->field("account,username,phone,idcard,company,realname,userlevel,viplevel,experience")->where("id=$uid")->find();
            $webVar['user']=$user;
            $this->assign($webVar);
            $this->display('WD_UserInfo:baseInfo');
        }catch (Exception $e){
            echo $e->getMessage();
            return;
        }

    }
}