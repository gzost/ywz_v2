<?php
/**
 * @file SIAction.class.php
 * SI服务接口，以类似Ajax用Json输出返回值。
 * 接口参数格式出错或权限不足，不返回输出，避免攻击
 * 
 * 调用方式：
 * http://<服务器URL>/SI/<action>/<参数名>/<参数值>...?account=<平台账号>&sec=<通信MD5字串>&tm=<有效时间戳>
 * 在平台上必须配置account具有action的操作权限
 * 在account的userExtAttr按约定填写commKey及SIdomain属性
 * 
 */

require_once APP_PATH.'../public/Ou.Function.php';
require_once APP_PATH.'Common/functions.php';
require_once LIB_PATH.'Model/UserModel.php';
require_once LIB_PATH.'Model/ActivestreamModel.php';
require_once LIB_PATH.'Model/StreamModel.php';
require_once LIB_PATH.'Model/RecordfileModel.php';
require_once APP_PATH.'../public/Authorize.Class.php';
//require_once LIB_PATH.'Action/HDPlayerAction.class.php';

class SIAction  extends Action {
	protected $author=null;	//授权对象
	protected $operStr=null;
	
	//主要是进行协议级校验
	function __construct(){
		parent::__construct();
		session_start();
		C('LOG_FILE','SIService%m%.log');
		C('LOGFILE_LEVEL','9');
		$httpHost=$_SERVER['HTTP_HOST'];	//
		$account=$_REQUEST['account'];	//SI账号
		$sec=$_REQUEST['sec'];	//客户端按算法得到的MD5字串
		$tm=$_REQUEST['tm'];	//请求的有效时间
		$uri=$_SERVER['PHP_SELF'];

		logfile(json_encode($_REQUEST),LogLevel::DEBUG);
		logfile($uri,LogLevel::DEBUG);
		if(ACTION_NAME=='tools') return;	//工具无需检查
		
		$this->author=new authorize();
		$this->author->logout();
		try{
			if(''==$account || ''==$sec || ''==$tm) throw new Exception('缺少必要的参数。');
			$dbUser=D('user');
			$attr=$dbUser->getExtAttrByAcc($account);
			logfile($dbUser->getLastSql(),LogLevel::SQL);
			logfile(json_encode($attr),LogLevel::SQL);
			if(null==$attr || ''==$attr['commKey']) throw new Exception('Can not find commKey!');
			$commKey=$attr['commKey'];
			//有效时间
			$now=sprintf("%x",time());
			if($tm<$now) throw new Exception('Expired:'.$tm.'<'.$now);
			//MD5校验
//echo $commKey.$uri.$account.$tm,'<br>';			
			$md5=MD5($commKey.$uri.$account.$tm);
			if($md5!=$sec) throw new Exception("MD5:".$commKey.$uri.$account.$tm.'='.$md5);
			
			//用户模拟登陆以便取得操作权限
			$dbUser=D('user');
			$md5passwd=$dbUser->where(array('account'=>$account))->getField(password);
			if(null===$md5passwd) throw new Exception('无此账号：'.$dbUser->getLastSql());
			$rt=$this->author->issue($account,$md5passwd);
			if(false===$rt) throw new Exception('账号登录失败');
			$this->operStr=$this->author->getOperStr(MODULE_NAME,ACTION_NAME);
//echo 	$this->operStr,'=',	MODULE_NAME,'=',	ACTION_NAME,'<br>';
			if(''==$this->operStr) throw new Exception('没有操作权限。');

			$this->author->setJustViewer();
			
		}catch (Exception $e){
			logfile($e->getMessage(),LogLevel::ERR);
//echo $e->getMessage();			
			exit;
		}
		return;
	}

	/**
	 * 供外部调用，发送短信
	 */
	public function sendSms123($phone, $code, $tmp)
	{
		//require_once APP_PATH.'../public/aliyun/Sms.Class.php';

		//$sms = new Sms();
		var_dump('sendSms');
		//echo $sms->sendSmsTmp($phone, $code, $tmp);
	}
	
	/**
	 * 
	 * 第三方用户登录播放
	 * 输入web变量：
	 * chnId	频道Id
	 * account	映射的观众账号
	 * nickname	可选变量，si观众的昵称，如果有会覆盖account在online表中的account字段，以便SI识别当前观看的观众
	 */
	public function play(){

//dump($_REQUEST);
//echo "play<br>";
		if(isset($_REQUEST['nickname'])) $_SESSION[authorize::USERINFO]['userName']=$_REQUEST['nickname'];
		if(!isset($_REQUEST['chnId'])){
			echo "缺乏频道参数";
		}

//echo "call HDPlayerAction/play<br>";
		$this->redirect('HDPlayer/play',array('chnId'=>$_REQUEST['chnId'],'xu'=>$_REQUEST['xu'],'xtl'=>$_REQUEST['xtl']));
		return;
	}

	/**
	 * 
	 * 第三方用户登录播放
	 * 输入web变量：
	 * vodid	点播文件Id
	 * account	映射的观众账号
	 * nickname	可选变量，si观众的昵称，如果有会覆盖account在online表中的account字段，以便SI识别当前观看的观众
	 */
	public function vod(){

//dump($_REQUEST);
//echo "play<br>";
		if(isset($_REQUEST['nickname'])) $_SESSION[authorize::USERINFO]['userName']=$_REQUEST['nickname'];
		if(!isset($_REQUEST['vodid'])){
			echo "缺乏点播文件参数";
		}
//echo "call HDPlayerAction/play<br>";

		//查看录像文件是否有关联频道
		$dbVod = new RecordfileModel();
		$rec = $dbVod->where(array('id'=>$_REQUEST['vodid']))->find();

		if(empty($rec))
		{
			echo "点播文件不存在";
		}
		else
		{
			if(0 < $rec['channelid'])
			{
				//有
				$this->redirect('HDPlayer/play',array('chnId'=>$rec['channelid'], 'r'=>$_REQUEST['vodid']));
				return;
			}
			else
			{
				//没有
				$this->redirect('HDPlayer/vod',array('vodId'=>$_REQUEST['vodid']));
				return;
			}
		}

	}
	
	/**
	 * 
	 * 更新正在录制录像文件大小
	 * 
	 * @param string	$stream	推流名称
	 * @param int	$size	录像文件大小MB
	 * 
	 * @return 输出json结果对象
	 */
	public function recordSize(){
		$stream=$_REQUEST['stream'];
		$size=$_REQUEST['size'];
		$arr=explode('-',$stream,2);
		$stream=$arr[0];
		if(null==$stream || null==$size) Oajax::errorReturn('没有流名称或size参数。');
		logfile("Stream=".$stream." size=".$size,LogLevel::DEBUG);
		$dbActivestream=D('activestream');
		$cond=array('name'=>$stream,'isactive'=>'true');
		$rt=$dbActivestream->where($cond)->setField('size',$size);
		logfile($dbActivestream->getLastSql(),LogLevel::SQL);
		Oajax::successReturn();
	}
	
	/**
	 * 取指定频道的播放地址
     * 通过get/post提供 stream或streamid参数，优先使用streamid
	 * 若account有频道的播放权限，则返回播放地址，否则返回出错信息。
	 */
	public function getPlayUri(){
		$streamName=$_REQUEST['stream'];
		$streamId=$_REQUEST['streamid'];
		$dbStream = new StreamModel();

		try{
			if(null==$streamName && null==$streamId) throw new Exception('Parameter was request: stream.');
	        //若没提供streamid则通过stream查找id
			if(null==$streamId){
                $streamId=$dbStream->getIdByName($streamName);
            }
            if(1>$streamId) throw new Exception('I do not know this stream:'.$streamName.'('.$streamId.')');
			//构造时已经以SI用户身份登录，检查SI是否是流的拥有者
			$userId = $this->author->getUserInfo('userId');
			$ret = $dbStream->isOwner($userId, $streamId);
			if(false == $ret) throw new Exception('You have no right.');


			$hls=$dbStream->getHls($streamId);
			if(''==$hls) throw new Exception('Stream configer error');
			Oajax::successReturn(array('uri'=>$hls));
		}catch (Exception $e){
			Oajax::errorReturn($e->getMessage());
		}
	}

	/**
	 * 
	 * 取指定录像文件的播放地址
	 * 若account有频道的播放权限，则返回播放地址，否则返回出错信息。
	 */
	public function getVodUri(){
		$vodid=$_REQUEST['vodid'];
		$dbVod = new RecordfileModel();
		$hls = '';
		try{
			if(null==$vodid) throw new Exception('Parameter was request:vodid.');

			$userId = $this->author->getUserInfo('userId');
			$ret = $dbVod->isOwner($userId, $vodid);

			if(false == $ret) throw new Exception('You have no right.');
			
			if(0 < $vodid)
			{
				$hls = $dbVod->getVodMrl($vodid);
			}
			else
			{
				throw new Exception('I do not know this vod:'.$vodid);
			}
			if(''==$hls) throw new Exception('Stream configer error');
			Oajax::successReturn(array('uri'=>$hls));
		}catch (Exception $e){
			Oajax::errorReturn($e->getMessage());
		}
	}

    /**
     * 返回SI或指定SI账号的流信息，及已经流是否正在推送属性
     * 找不到返回空数组
     * @param int $siuser   查询属于此用户的流，否则查询整个SI的流
     * @param int $streamid
     */
    public function getStreamList($siuser=0,$streamid=0){
	    $db=new Model();
	    $sql ='select A.id,idstring,status,A.name,siuser,isactive from __PREFIX__stream A ';
	    $sql.='left join __PREFIX__activestream B on A.id=streamid where owner='.$this->author->getUserInfo('userId');
	    if($siuser>0) $sql .=' and siuser='.$siuser;
	    if($streamid>0) $sql .=' and A.id='.$streamid;

	    $recs=$db->query($sql);
	    //$recs['sql']=$db->getLastSql();
	    //echo $db->getLastSql();
	    //dump($recs);
        Oajax::successReturn(array('stream'=>$recs));
    }

    /**
     * 取录像文件列表
     * @param int $siuser   录像文件关联的SI用户ID
     * @param int $sichannel    录像文件关联的SI频道ID
     */
    public function getVodList($siuser=0,$sichannel=0){
        $db=D('recordfile');
        $cond=array('owner'=>$this->author->getUserInfo('userId'));
        if(0<$siuser) $cond['siuser']=$siuser;
        if(0<$sichannel) $cond['sichannel']=$sichannel;
        $fields='id,size,length,viewers,createtime,path,name,descript,siuser,sichannel';
        $recs=$db->field($fields)->where($cond)->order('seq')->select();
//echo $db->getLastSql();
        //取图片url
        $host='http://'.$_SERVER['HTTP_HOST'];
        foreach ($recs as $key=>$row){
            $subPath=$row['path'];
//echo $subPath,'<br>';
            $recs[$key]['imgUrl']=$host.$db->getImgMrl($subPath);
            unset($recs[$key]['path']);
//echo $imgUrl,'<br>';
        }
        Oajax::successReturn(array('list'=>$recs));
    }
	
	public function tools(){
		$httpHost=$_SERVER['HTTP_HOST'];
		echo $httpHost;
		$tpl=array('commKey'=>'ywzkey','uri'=>'/player.php/SI/play/chnId/100','account'=>'test','tm'=>sprintf("%x",time()+600));
		$webVar=getRec($tpl,false);
		$webVar['now']=sprintf("%x",time());
		$md5=MD5($webVar['commKey'].$webVar['uri'].$webVar['account'].$webVar['tm']);
		$webVar['callStr']=sprintf("http://%s%s?account=%s&sec=%s&tm=%s",$httpHost,$webVar['uri'],$webVar['account'],$md5,$webVar['tm']);
//dump($webVar);
		$this->assign($webVar);
		$this->display(tools);
	}
}
?>