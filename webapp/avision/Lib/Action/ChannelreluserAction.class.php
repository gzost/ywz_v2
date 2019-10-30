<?php
/**
 * 关于频道会员的页面响应
 * User: outao
 * Date: 2019/10/30
 * Time: 10:46
 */

require_once APP_PATH.'../public/SafeAction.Class.php';
require_once APP_PATH.'../public/Ou.Function.php';

class ChannelreluserAction extends SafeAction{

    /**
     * 修改会员对频道的打分
     * 使用本功能时，必须有用户登录
     * 通过POST传递：cruId-频道用户关联表(channelreluser)id, score-分值
     *
     * 通过json返回两个属性：success-成功'true', 失败'false'；msg-对结果的提示字串
     */
    public function saveScoreJson(){
        try{
            $uid=$this->userId();
            //var_dump($uid,$_POST);
            if(empty($uid)) throw new Exception('权限受限');
            if(!Oajax::needAttr($_POST,'score,cruId',false)) throw new Exception('参数不足');
            $score=intval($_POST['score']);
            $cruId=intval($_POST['cruId']);
            if(empty($score)||empty($cruId)) throw new Exception("参数越界");

            $dbChannelreluser=D('channelreluser');
            $rt=$dbChannelreluser->where('id='.$cruId)->save(array('score'=>$score));
            if(false===$rt) throw new Exception('无法保存数据');
        }catch (Exception $e){
            Oajax::errorReturn($e->getMessage());
        }
        Oajax::successReturn();
    }
}