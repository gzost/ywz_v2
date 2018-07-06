<?php
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once LIB_PATH.'Model/RecordfileDetailViewModel.php';
require_once LIB_PATH.'Model/RecordfileModel.php';
require_once LIB_PATH.'Model/ChannelModel.php';
require_once LIB_PATH.'Model/UserModel.php';
//require_once APP_PATH.'../public/FileUpload.Class.php';
require_once APP_PATH.'../public/uploadhandler.php';

class VodAction extends AdminBaseAction {

	public function  t(){
		$str='{"multiplelogin":1,"discuss":"normal","wxonly":"true","viewIncRand":"20",
			"livetime":"2017-04-30 08:00:00","livekeep":"","logo":"logo.jpg","cover":"cover.jpg",
			"info":"{\"0\":{\"img\":\"\/room\/010\/84\/info\/1493549774.jpg\",\"link\":\"tel:18819036429\"},\"1\":{\"text\":\"\u4eca\u665a\u4e1c\u65b9\u9ed1\u6c60\u9891\u9053\u540c\u65f6\u76f4\u64ad\u91d1\u534e\u7684\u665a\u4f1a,\u6b22\u8fce\u70b9\u51fb\u6536\u770b\"},\"2\":{\"img\":\"\/room\/010\/84\/info\/1493550053.jpg\",\"link\":\"https:\/\/open.weixin.qq.com\/connect\/qrconnect?appid=wx046e7448f1c40755\"}}",
			"userbill":{"isbill":"false","billday24":"","billmonth":"","billday7":"","billday30":""},
			"viewerlimit":"999999"}';
		$j=json_decode($str,TRUE);
		dump($j);
		
		$s='hmc128GT3S0L-1492747136all.flv';
		$arr=explode('-',$s,2);
		echo substr($arr[1],0,10);
		dump($arr);
	}
	/**
	 * 
	 * 录像管理列表主界面
	 * 
	 */
	const VODFILEINDEX='VodFileListIndex';
	public function fileList(){
		$this->baseAssign();
		$webVarTpl=array('name'=>'','channelname'=>'','beginTime'=>'','endTime'=>'','ownerAccount'=>'','ownerId'=>0);
		$webVar=getRec($webVarTpl,false);
//dump($webVar);			
		$webVar['mainTitle']='录像文件管理';
		if($this->isOpPermit('A')||'true'==$this->isAdmin){	//是否只能观看自己(没观看所有的 )
 			$webVar['viewSelf']='false';
 			if(''!=$webVar['ownerAccount']){
 				$dbUser=D('user');
 				$webVar['ownerId']=$dbUser->getUserId($webVar['ownerAccount']);
 				if(1>$webVar['ownerId']) $webVar['msg']='属主不存在！';
 			}
 		}else{
 			$webVar['viewSelf']='true';
 			$webVar['ownerAccount']=$this->getUserInfo('account');	//默认只查找当前用户所属频道/VOD的用户
 			$webVar['ownerId']=$this->userId();
 		}
		//上传（新建）录像文件权限
		$webVar['permitCreate']=($this->isOpPermit('C'))?'true':'false';
		//修改、删除录像记录权限
		$webVar['permitModify']=($this->isOpPermit('M'))?'true':'false';
		
//dump($webVar);		
		//生成符合Thinkphp语法的查询条件数组
		$cond=array();
		if('true'==$webVar['viewSelf']) $cond['owner']=$this->userId();
		else{
			if(''!=$webVar['ownerAccount']) $cond['account']=$webVar['ownerAccount'];
		}
		if(''!=$webVar['name']) $cond['name']=array('LIKE',$webVar['name'].'%');
		$rt=$this->betweenDate($webVar['beginTime'], $webVar['endTime']);
		if(null!=$rt) $cond['createtime']=$rt;
//dump($cond);
		$db=D('RecordfileDetailView');
		$result=$db->getIdList($cond);
		pagination::setData(self::VODFILEINDEX, $result);
		
		$this->assign($webVar);
    	$this->show('fileList');
	}
	
	/**
	 * 
	 * 生成>=$bd and <$ed 的查询条件，ThinkPHP语法
	 * 查询数据库字段是datetime类型
	 * @param string $bd	开始日期
	 * @param string $ed	结束日期
	 */
	protected function betweenDate($bd,$ed){
//var_dump($bd,$ed);		
		if(''==$bd && ''==$ed) return null;
		elseif(''==$ed) return array('EGT',$bd);
		$ed=date('Y-m-d',strtotime('+1 day',strtotime($ed)));
		if(''==$bd) return array('LT',$ed);
		else return array('BETWEEN',array($bd,$ed));
	}
	
	public function getFileListAjax($page=1,$rows=10){
		//提取记录数据
		$index=pagination::getData(self::VODFILEINDEX,$page,$rows);
//var_dump($index);
		if(1>$rows) { echo '[]'; return; }
		
		$db=D('RecordfileDetailView');
		$data=pagination::getRecDetail($db,$index);
		
//echo $db->getLastSql();		
		$result=array();
		$result["rows"]=$data;
		$result["total"]=$rows;
		//$result["footer"][]=$total;
		if(null==$result)	echo '[]';
		else echo json_encode($result);
	}
	
	static $modifyField=array(
		'name'=>array('name'=>'name','txt'=>'录像标题'),
		'descript'=>array('name'=>'descript','txt'=>'描述'),
		'createtime'=>array('name'=>'createtime','txt'=>'录像时间'),
		'length'=>array('name'=>'length','txt'=>'录像时长'),
		'channelid'=>array('name'=>'channelid','txt'=>'关联频道'),
		'account'=>array('name'=>'account','txt'=>'属主')
	);
	/**
	 * 
	 * 取录像文件的详细资料
	 * 由fileList生成的datagrid.onSelect调用，$_POST包括当前的datagrid记录各字段
	 */
	public function getDetailAjax(){
		$para=getRec(RecordfileDetailViewModel::$fieldTpl,false);
		$this->showDetail($para,false);
	}
	
	public function number2Text($num=null){
		static $key='ABCEDFGHIJKLMNOPQRSTUVWXYZ_0123456789';
		$keyLen=strlen($key);
		
		if(null===$num){
			list($mic, $num) = explode(' ', microtime());
//echo $mic,'<br>',$num,'<br>',time(),'<br>';	
			$mic *=1000;
			for($i=0; $i!=2; $i++){
				$str =$key[$mic%$keyLen].$str;
				$mic=intval($mic/$keyLen);
			}
			//$num =  (float)sprintf('%.0f', (floatval($tmp1) + floatval($tmp2)) * 1000);
		}else {
			$str='';
		}
		if(!is_numeric($num)) return '';
		
//echo $keyLen,'<br>',$num,'<br>';
		
		while(0!=$num){
			$str =$key[$num%$keyLen].$str;
			$num=intval($num/$keyLen);
//echo $str,'-',$num,'<br>';			
		}
//echo $str;
		return $str;
	}
	//显示新增录像文件界面
	public function addAjax($owner,$account){
		//要建立了记录后才好上传录像及封面图片
		$rec=array('owner'=>$owner,'createtime'=>date('Y-m-d H:i:s'));
		$dbRecord=D('recordfile');
		$rec['path']=$dbRecord->createSubDir($owner);
		$rec['path'] .='/'.$owner.'_'.$this->number2Text().'.mp4';
		$rec['name']='新录像'.date('Y-m-d H:i:s');
		$id=$dbRecord->add($rec);
		$rec['id']=$id;
		$rec['account']=$account;
		$this->showDetail($rec,false);
	}
	/**
	 * 
	 * 显示$rec记录
	 * 
	 * @param array $rec	录像文件数据记录若为,新记录时包括owner,account
	 */
	public function showDetail($rec,$new=false){
		
		$mf=self::$modifyField;
logfile(json_encode($rec),LogLevel::DEBUG);
		if($new){
			if($this->isOpPermit('A')){
				//可设置所有
				//$rec['owner']=0;
				//$rec['account']='';
			}else{
				$rec['owner']=$this->userId();
				$rec['account']=$this->getUserInfo('account');
			}
		}
		$mf['account']['valattr']=" READONLY ";
		$mf['account']['valclass']="noboder";
		foreach ($mf as $key=>$field){
			$mf[$key]['val']=$rec[$key];
		}
//dump($rec);		
		//关联频道
		$dbChn=D('channel');
		$chnList=$dbChn->getListByOwner($rec['owner'],$order='id desc',$fields='id as val,name as txt');
		array_push($chnList, array("val"=>"0", "txt"=>"不选择"));
		$chnJson=str_replace('"',"'",json_encode($chnList));
		
		$mf['descript']['valattr']=" data-options=\"multiline:true, height:60, width:320 \" ";
		$mf['descript']['valclass']="easyui-textbox";
		$mf['channelid']['valattr']=" data-options=\" width:320,valueField:'val',textField:'txt',
			data:".$chnJson." \" ";
		$mf['channelid']['valclass']="easyui-combobox";
		
		
//echo $dbChn->getLastSql();
//dump($chnList);
		$webVar=$rec;	
		$webVar['detailFormData']=OUdetailform($mf);
		$webVar['imageUrl']=RecordfileModel::getImgMrl($rec['path']);
		$webVar['permitCreate']=($_REQUEST['permitCreate']=='true')?'true':'false';
		$webVar['permitModify']=($_REQUEST['permitModify']=='true')?'true':'false';
		if($new){
			$webVar['title']='新上传录像';
			$webVar['new']='true';
		}else {
			$webVar['title']='录像ID：'.$rec['id'].'　标题：'.$rec['name'];
			$webVar['new']='false';
			$webVar['id']=$rec['id'];
			$webVar['path']=$rec['path'];
			$webVar['account']=$rec['account'];
			$webVar['size']=$rec['size'];
		}

		$this->assign($webVar);
		$this->display('showDetail');
	}
	
	//更新录像记录
	public function updateAjax(){
		$rec=array('owner'=>0,'channelid'=>0,'length'=>'','createtime'=>'','name'=>'','descript'=>'');
		$rec=getRec($rec,TRUE);
//dump($_REQUEST);
		$dbUser=D('user');
		try{
			if(0==$_POST['owner']){
				if(''==$_POST['account']) throw new Exception('必须指定录像所属的播主。');
				$rec['owner']=$dbUser->getUserId($_POST['account']);
			}
			if(null==$rec['owner']) throw new Exception('找不到指定的播主。');
			//if(0==$rec['channelid']) throw new Exception('必须指定录像关联的频道。');
			if(1>strlen($rec['name'])) throw new Exception('必须填写录像标题。');
			$dbRf=D('recordfile');
			if('true'==$_POST['new']){	
				$rt=$dbRf->add($rec);
				if(false===$rt) throw new Exception('新增失败，请通知管理员。');
			}else{
				$rt=$dbRf->where('id='.$_POST['id'])->save($rec);
				if(false===$rt) throw new Exception('更新失败，请通知管理员。');
			}
			
		}catch (Exception $ex){
			Oajax::errorReturn($ex->getMessage());
			return;
		}
//dump($rec);
		Oajax::successReturn();
		return;		
	}
	
	
	
	//删除录像记录及文件
	public function deleteAjax($id,$path){
		//$path='/000/020/173/1.mp4';
		$dbRf=D('recordfile');
		try{
			if(1>$id) throw new Exception('参数错误！');
			$rt=$dbRf->where('id='.$id)->delete();
			if(false===$rt) throw new Exception('无法删除！');
			//删除视频文件及图片
			$rt=$dbRf->where(array('path'=>$path.'tt'))->count();
			if('0'===$rt){
				//已经没有其他记录引用此视频文件
				$path=substr($path,0,strrpos($path,'.')+1);
				$base=(''==C(vodfile_base_path))?'/vodfile':C(vodfile_base_path);
				$base=$_SERVER['DOCUMENT_ROOT'].$base;
				$rt=unlink($base.$path.'mp4');
//var_dump($rt);
				$rt=unlink($base.$path.'jpg');
//var_dump($base.$path,$rt);				
			}
//echo $dbRf->getLastSql();
		}catch (Exception $e){
			echo 'Error:',$e->getMessage();
			return;
		}
		echo '删除成功！';
		return;
	}

	/**
	 * 
	 * 文件上传的后台处理程序
	 */
	public function endpoint() {
		$uploader = new UploadHandler ();
		
		// Specify the list of valid extensions, ex. array("jpeg", "xml", "bmp")
		$uploader->allowedExtensions = array (); // all files types allowed by default
		

		// Specify max file size in bytes.
		$uploader->sizeLimit = null;
		
		// Specify the input name set in the javascript.
		$uploader->inputName = "qqfile"; // matches Fine Uploader's default inputName value by default
		

		// If you want to use the chunking/resume feature, specify the folder to temporarily save parts.
		$uploader->chunksFolder = "chunks";
		
		$method = $this->get_request_method ();
		
		//Insert by outao 2017-04-10
		$uploader->useUuid=false;	//不建立UUID子目录
		//$dbRecord=D('recordfile');
		//$vodpath=$dbRecord->createSubDir($_REQUEST['owner']);
		//$vodname=$_REQUEST['owner'].'_'.time();
		$basePath=(''==C(vodfile_base_path))?'/vodfile':C(vodfile_base_path);
		$info = pathinfo($_REQUEST['path']);
		$vodpath=$basePath.$info['dirname'];
		$vodpath=substr($vodpath,1);
		$vodname=substr($info['basename'],0,strrpos($info['basename'],'.'));
		//$info = pathinfo($uploader->getName());
		//$vodname =$vodname.'.'.$info['extension'];
		if($_REQUEST['type']=='cover') $ext='jpg';
		else $ext='mp4';
		$vodname =$vodname.'.'.$ext;
logfile('Upload path:'.$vodpath.' name:'.$vodname,LogLevel::INFO);
		
		// This will retrieve the "intended" request method.  Normally, this is the
		// actual method of the request.  Sometimes, though, the intended request method
		// must be hidden in the parameters of the request.  For example, when attempting to
		// delete a file using a POST request. In that case, "DELETE" will be sent along with
		// the request in a "_method" parameter.
		
logfile(json_encode($_REQUEST),LogLevel::DEBUG);
		if ($method == "POST") {
			header ( "Content-Type: text/plain" );
	
			// Assumes you have a chunking.success.endpoint set to point here with a query parameter of "done".
			// For example: /myserver/handlers/endpoint.php?done
			if (isset ( $_GET ["done"] )) {
				$result = $uploader->combineChunks ( $vodpath, $vodname );
logfile('call:combineChunks return:'.$result,LogLevel::DEBUG);
			} // Handles upload requests
			else {
				// Call handleUpload() with the name of the folder, relative to PHP's getcwd()
				
				$result = $uploader->handleUpload ( $vodpath, $vodname);
//logfile('call：handleUpload return:'.$result,LogLevel::DEBUG);				
				// To return a name used for uploaded file you can use the following line.
				$result ["uploadName"] = $uploader->getUploadName ();
//logfile('file:'.$result ["uploadName"],LogLevel::DEBUG);
			}
			
			echo json_encode ( $result );
		} // for delete file requests
		else if ($method == "DELETE") {
			$result = $uploader->handleDelete ( $vodpath, $vodname );
			echo json_encode ( $result );
		} else {
			header ( "HTTP/1.0 405 Method Not Allowed" );
		}
	}
	
	protected function get_request_method() {
		global $HTTP_RAW_POST_DATA;
		
		if (isset ( $HTTP_RAW_POST_DATA )) {
			parse_str ( $HTTP_RAW_POST_DATA, $_POST );
		}
		
		if (isset ( $_POST ["_method"] ) && $_POST ["_method"] != null) {
			return $_POST ["_method"];
		}
		
		return $_SERVER ["REQUEST_METHOD"];
	}
	/**
	 * 
	 * 上传封面图片成功
	 */
	public function postUploadCoverAjax(){
		logfile(json_encode($_REQUEST),LogLevel::DEBUG);
		$dbRf=D('recordfile');
		$arr=array();
		$arr['imgUrl']=RecordfileModel::getImgMrl($_REQUEST['path']);
		Oajax::successReturn($arr);
		logfile('end....',LogLevel::DEBUG);
	}
/**
	 * 
	 * 上传视频成功
	 */
	public function postUploadVideoAjax(){
		logfile(json_encode($_REQUEST),LogLevel::DEBUG);
		$dbRf=D('recordfile');
		$fileSize=$dbRf->updateVideoSize($_REQUEST['recordId']);
logfile('recordId='.$_REQUEST['recordId'].' size='.$fileSize,LogLevel::DEBUG);
		$arr=array();
		$arr['fileSize']=$fileSize;
		Oajax::successReturn($arr);
		
	}
	
	/**
	 * 
	 * 维护录像记录与录像文件的一致性
	 * 
	 */
	const MIN_FILESIZE=200000;
	public function maintenance($work='init'){
		$this->baseAssign();
//$count=preg_match("/(^\/[0-9]{3})\/[0-9]{3}\/[0-9]{3}$/", '/000/123/456',$matches);
//$count=preg_match("/^(http:\/\/)?([^\/]+)/i",   "http://www.php.net/index.html", $matches);

//var_dump($count,$matches)	;
		$progressFile=getcwd().'/recordfileprogress.html';
//echo 	$progressFile;
		unlink($progressFile);
		error_log('开始维护......<br>',3,$progressFile);
		if('maint'==$work){
			$db=D('recordfile');
			$rec=$db->field('id,size,path')->select();
//echo $db->getLastSql();			
			error_log('现有录像记录数：'.count($rec).'<br>',3,$progressFile);
			foreach ($rec as $col){
				
				if(0===stripos($col['path'],'http')) continue;	//http开头的记录不处理
				$fullPath=getcwd().C('vodfile_base_path').$col['path'];
				$size=filesize($fullPath);
				if(false===$size){
					error_log('<br>录像文件丢失：'.$fullPath.'',3,$progressFile);
					//$data=array('size'=>0,'length'=>'','discript'=>'文件丢失！');
					$db->where('id='.$col['id'])->delete();
					continue;
				}
				if($size<self::MIN_FILESIZE){
					error_log('<br>录像文件错误，强制删除：'.$fullPath.'',3,$progressFile);
					$file=substr($fullPath, 0,-3).'*';
					$this->deleteFiles($file);	//删除文件
					$db->where('id='.$col['id'])->delete();	//删除记录
					continue;
				}
				$size=ceil($size/1000000);	//文件大小MB

				$diff=abs($col['size']-$size)/($size+0.0001);
				if($diff>0.05){
					//文件size误差大于5%，修改数据库记录
					$db->where('id='.$col['id'])->setField('size',$size);
					error_log('<br>调整文件大小：'.$fullPath.'['.$col['size'].'->'.$size.']',3,$progressFile);
					continue;
				}
				error_log('.',3,$progressFile);
				
				usleep(100000);	//1毫秒
			}			
			error_log('<br>开始扫描录像文件目录......<br>',3,$progressFile);
			$baseDir=getcwd().C('vodfile_base_path');
			$para=array('progressFile'=>$progressFile);
			$fileList=$this->scanMyDir($baseDir,"VodAction::procFile",$para);
			error_log('<br>维护完成......<br>',3,$progressFile);
			return;
		}
		$webVar=array('path'=>'/recordfileprogress.html');
		$this->assign($webVar);
		$this->display('maintenance');
	}
	//删除指定目录下的文件，支持通配符
	protected function deleteFiles($path){
		$fileList=glob($path);
		if(false==$fileList) return;
		array_map("unlink", $fileList);
	}
	protected function scanMyDir($path,$callBack,&$para){
//echo 	$path,'<br>';
		$dh=opendir($path);
		while(($file=readdir($dh))!==false){
			if($file == '.' || $file == '..') continue;
			$fullpath=$path.'/'.$file;
			if(is_dir($fullpath)){
				$this->scanMyDir($fullpath,$callBack,$para);	//递归下一目录
			}else{
				$params=array($fullpath,$para);
				$rt=call_user_func_array( $callBack , $params );
			}
		}

	}
	static function procFile($path,$para){
		$msg='.';
		$info=pathinfo($path);	//dirname,basename,extension,filename
		$mp4file=$info['dirname'].'/'.$info['filename'].'.mp4';
		$jpgfile=$info['dirname'].'/'.$info['filename'].'.jpg';
		if($info['extension']=='jpg'){
			//jpg要检查是否有对应的mp4
			if(!is_file($mp4file)){ 
				//unlink($path);	//没有则删除
				$msg='没有对应的mp4，删除：'.$path;
			}
		}elseif ($info['extension']=='mp4'){
			//删除体积过小的文件
			$size=filesize($path);
			if($size<self::MIN_FILESIZE){
				//删除过小的文件
				$msg='文件过小，删除：'.$path;
				unlink($jpgfile);	//删除文件
				unlink($mp4file);
			}else{
				//测试录像文件是否已在记录中
				$file=substr($info['dirname'], -12).'/'.$info['filename'].'.mp4';
				$cond=array('path'=>$file);
				$db=D('recordfile');
				$count=$db->where($cond)->count();
//echo $db->getLastSql().'<br>';
//var_dump($count);				
				if($count==='0'){
					//找不到
					//$owner=str_replace('/', '',substr($info['dirname'], -12));
					$owner=substr($info['dirname'], -12);
					$count=preg_match("/(^\/[0-9]{3})\/[0-9]{3}\/[0-9]{3}$/", $owner);
var_dump($count,$owner);
					if($count>0){
						$owner=str_replace('/', '',$owner);
						$owner=ltrim($owner,'0');
echo 'owner='.$owner;					
						$data=array('owner'=>$owner,'size'=>ceil($size/1000000),'createtime'=>date('Y-m-d H:i:s'),
							'path'=>$file,'name'=>$info['basename']);
						$id=$db->add($data);
						$data['id']=$id;
						$msg='插入记录：'.json_encode($data);
					}
					
				}
			}
		}else{
			//不是jpe,mp4扩展名的文件删除
			unlink($path);
			$msg='未知用途文件，删除：'.$path;
		}
		if('.'==$msg) error_log($msg,3,$para['progressFile']);
		else error_log('<br>'.$msg,3,$para['progressFile']);
	}

}
?>