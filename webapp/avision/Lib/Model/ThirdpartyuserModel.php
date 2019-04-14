<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2018-4-21
 * Time: 11:37
 */
require_once(LIB_PATH.'Model/UserModel.php');
class party{
    //第三方平台代号
    const YLH = 'YLH';  //云联惠
    const WX  = 'WX';   //微信
    const WB  = 'WB';   //微博
}
class ThirdpartyuserModel extends Model{


    /**
     * 通过第三方账号信息，查询YWZ平台的用户ID
     * @param $party
     * @param $openid
     * @return int
     *  - >0 用户ID
     *  - 0|unll  找不到用户（未注册）
     */
    public function getUserId($party,$openid){
        if(strlen($party)<2 || strlen($openid)<6) return 0;
        $cond=array('party'=>$party,'openid'=>$openid);
        $uid=$this->where($cond)->getField('userid');
        return $uid;
    }

    /**
     * 根据第三方（第一次）登录信息注册新的YWZ用户
     * @param $authInfo
     * @return int  新用户的ID
     * @throws  失败时抛出错误信息
     */
    public function register($authInfo){
        $this->startTrans();
        //新YWZ用户的数据
        $record=array(
            'account'=>party::YLH.$authInfo['openid'],
            'username'=>$authInfo['nickname'],
            'password'=>date('Ymd').mt_rand(100000,999999)  //这是个不可能密码，只能通过第三方登录
            //'phone'=>$authInfo['mobile']  //YWZ设计电话号码有唯一性要求，这里不能主动填写
        );
        $dbUser=D('User');
        try{
            $userid=$dbUser->adduser($record);

//echo $dbUser->getLastSql();
            //第三方用户信息
            $record=array(
                'userid'=>$userid,
                'party'=>$authInfo['party'],
                'openid'=>$authInfo['openid'],
                'nickname'=>$authInfo['nickname'],
                'access_token'=>$authInfo['access_token'],
                'expires_in'=>intval($authInfo['expires_in'])+time(),
                'token_type'=>$authInfo['token_type'],
                'scope'=>$authInfo['scope'],
                'refresh_token'=>$authInfo['refresh_token'],
                'mobile'=>$authInfo['mobile'],
                'rcm_id'=>$authInfo['rcm_id']
            );
            $id=$this->add($record);
//echo $this->getLastSql();
            if($id<1) throw new Exception('不能创建第三方用户信息.');
        }catch (Exception $e) {
            $this->rollback();
            logfile($e->getMessage(),LogLevel::WARN);
            throw new Exception($e->getMessage());
        }
        $this->commit();
        return $userid;
    }

    /**
     * 更新第三方用户信息特别是token
     * @param $authInfo
     * @param $party
     * @param $userid
     * @throws Exception
     */
    public function update($authInfo,$userid=null,$party=null){
        $attr=array('nickname','access_token','token_type','scope','refresh_token','mobile','rcm_id');

        $cond=array();
        if( null != $authInfo['openid'] )
            $cond['openid']=$authInfo['openid'];
        else
            $cond['userid']=(null==$userid)?$authInfo['userid']:$userid;

        $cond['party']=(null==$party)?$authInfo['party']:$party;

        if($cond['party']==null || $cond['openid']==null && $cond['userid']==null )
            throw new Exception('缺乏必要的参数。');
            //=array('openid'=>$authInfo['openid'],'party'=>$authInfo['party']);
        $record=array();
        foreach ($attr as $a){
           if(isset($authInfo[$a]) && $authInfo[$a]!==null) $record[$a]=$authInfo[$a];
        }
        if(isset($authInfo['expires_in']) && $authInfo['expires_in']!==null) $record['expires_in']=$authInfo['expires_in']+time();

        $rt=$this->where($cond)->save($record);
        if($rt!=1){
            //不是一条记录更新都有异常
            logfile("Update false:".$rt." SQL:".$this->getLastSql(),LogLevel::WARN);
            //暂不抛出错误
        }
    }



    /**
     * 查找refresh_token
     * @param $userid
     * @param $party
     * @throws Exception
     */
    public function getRefreshToken($userid,$party){
        $cond=array('userid'=>intval($userid),'party'=>$party);
        $token=$this->where($cond)->getField('refresh_token');

        if(null==$token) throw new Exception('找不到token');
        return $token;
    }

    public function getAccessToken($userid,$party){
        $cond=array('userid'=>intval($userid),'party'=>$party);
        $token=$this->where($cond)->getField('access_token');

        if(null==$token) throw new Exception('找不到token');
        return $token;
    }
}


?>