<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/12/15
 * Time: 17:43
 * 通讯模块后端
 */

require_once APP_PATH.'../public/SafeAction.Class.php';
require_once(LIB_PATH.'Model/OnlineModel.php');


class BE_communicateAction extends SafeAction{

    /**
     * 响应前端通讯请求，请求POST以下参数
     *  -tokenName: 通讯令牌名称
     *  -tokenValue: 令牌值
     *  -sendTime: 终端发送时的时间戳(毫秒)
     *  -playload: array数据 {""数据类型1"=><数据值>, "数据类型2"=><> }
     * 目前支持的数据类型：
     *  onlineTable-在线记录数组，
     *  chat-聊天数据
     *  exercise-堂上练习
     *  appPara-应用相关的参数，如频道ID，当前登录用户信息等{chnid:频道ID, user:{uid:用户ID, userName:用户昵称,account:用户账号}}
     * 数据值根据数据类型不同为单值或数组
     *
     * 返回：json字串
     *  -成功 {"success":"true","类型1":<>, "类型2"<>}
     *  -失败 {"success":"false","msg":"出错信息"}
     */
    public function server(){
        try{
            if(empty($_POST["tokenName"] || empty($_POST["tokenValue"]))) throw new Exception("参数错误");
            if(!contextToken::verifyToken($_POST["tokenName"],$_POST["tokenValue"])) throw new Exception("非法访问");

            $clientStamp=intval($_POST["sendTime"]/1000);   //转换为以秒为单位的时间戳
            $clientUserInfo=$_POST["playload"]["appPara"]["user"]; //客户端的用户信息
            $uid=(empty($clientUserInfo["uid"]))?$this->userId(): $clientUserInfo["uid"];
            $userName=(empty($clientUserInfo["userName"]))?$this->userName():$clientUserInfo["userName"];
            $chnid=$_POST["playload"]["appPara"]["chnid"];
            $chnid=(empty($chnid))?0 : intval($chnid);
//var_dump($uid,$userName);
            $retArr=array();
            //无论前端是否发送了在线记录数组，都要更新在线记录，同时通知前端是否需要下线
            $dbOnline=D("online");
            $FE_recs=(is_array($_POST["playload"]["onlineTable"]))?$_POST["playload"]["onlineTable"]:array();
            $retArr["onlineTable"]=$dbOnline->updateOnline($FE_recs,$uid,$userName,$clientStamp,$chnid);

            //处理聊天通讯
            $actionWebChat=A("WebChat3");
            $rt=$actionWebChat->communicate($_POST["playload"]["appPara"]["chat"],$_POST["playload"]["chat"]);
            if(is_array($rt)) $retArr["chat"]=$rt;

            //处理堂上练习
            $app=A("FE_exercise");
            $rt=$app->communicate(session_id(),$_POST["playload"]["appPara"],$_POST["playload"]["exercise"]);
            if(is_array($rt)) $retArr["exercise"]=$rt;

            //更新页面元素
            $retArr['element']=$this->getElement($_POST["playload"]["appPara"]['chnid']);
//$retArr["userid"]=$this->userId();
//dump($retArr);
            Oajax::successReturn($retArr);
        }catch (Exception $ex){
            Oajax::errorReturn($ex->getMessage());
        }
    }

    //取指定频道的点击次数，当需要更新的元素多后，这部分独立成一个后天Action去做
    public function getElement($chnid=-1){
        $rtArray=array();
        $chndb=D("channel");
        $entrytimes=$chndb->where("id=".$chnid)->getField("entrytimes");
        $rtArray["UE_entrytimes"]=($entrytimes>0)?$entrytimes:0;
        return $rtArray;
    }
}
?>