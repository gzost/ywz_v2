<?php
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once LIB_PATH.'Model/RecordfileDetailViewModel.php';
require_once LIB_PATH.'Model/RecordfileModel.php';
require_once LIB_PATH.'Model/ChannelModel.php';
require_once LIB_PATH.'Model/UserModel.php';
//require_once APP_PATH.'../public/FileUpload.Class.php';
require_once APP_PATH.'../public/uploadhandler.php';

class VodAction extends AdminBaseAction {

    const VODACTION_TOKEN="vodFileListToken";    //传递页面上下文，的访问令牌，用于校验请求来自fileList方法生成的页面
	public function  t(){
		$db=D('Recordfile');
		$str=$db->getVodMrl(10197);
		dump($str);
	}
	/**
	 * 
	 * 录像管理列表主界面
	 * {"operation":[{"text":"允许","val":"R"},{"text":"所有","val":"A"},{"text":"上传","val":"C"},{"text":"修改","val":"M"},{"text":"下载","val":"S"}]}
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
        $bozhu=$this->getUserInfo('bozhu');
		if("junior"==$bozhu) $webVar['permitCreate']='false';   //初级播主不能上传和分享录像
		else	$webVar['permitCreate']=($this->isOpPermit('C'))?'true':'false';
		//修改、删除录像记录权限
		$webVar['permitModify']=($this->isOpPermit('M'))?'true':'false';
        $webVar['permitDownload']=($this->isOpPermit('S'))?'true':'false';
		
//dump($webVar);		
		//生成符合Thinkphp语法的查询条件数组
		$cond=array();
		if('true'==$webVar['viewSelf']) $cond['owner']=$this->userId();
		else{
			if(''!=$webVar['ownerAccount']) $cond['account']=$webVar['ownerAccount'];
		}
		if(''!=$webVar['name']) $cond['name']=array('LIKE','%'.$webVar['name'].'%');
		if(isset($webVar['channelname']) && ''!=$webVar['channelname']) $cond['channelname']=array('LIKE','%'.$webVar['channelname'].'%');
		$rt=$this->betweenDate($webVar['beginTime'], $webVar['endTime']);
		if(null!=$rt) $cond['createtime']=$rt;
//dump($cond);
        condition::save($cond,'VODfilelist');
		//$db=D('RecordfileDetailView');
		//$result=$db->getIdList($cond);
		//pagination::setData(self::VODFILEINDEX, $result);

		$webVar[self::VODACTION_TOKEN]=contextToken::newToken(self::VODACTION_TOKEN);
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
	
	public function getFileListAjax($page=1,$rows=10,$sort='viewers',$order='desc'){
		//提取记录数据
		//$index=pagination::getData(self::VODFILEINDEX,$page,$rows);
//var_dump($index);
		//if(1>$rows) { echo '[]'; return; }
		
		$db=D('RecordfileDetailView');
		//$data=pagination::getRecDetail($db,$index);
		$cond=condition::get('VODfilelist');
//var_dump($cond);
		if(isset($cond)){   //必须要有条件变量才进行查询
            $data=$db->where($cond)->order("$sort $order")->page($page,$rows)->select();
            $rows=$db->where($cond)->count();
            $result=array();
            $result["rows"]=$data;
            $result["total"]=$rows;
        }else
            $result=null;


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
        'seq'=>array('name'=>'seq','txt'=>'显示顺序'),
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
		$rec['size']=0; //size=0作为没有录像文件标志
        $rec['sourceid']=0;
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
logfile(json_encode2($rec),LogLevel::DEBUG);
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
		$webVar['imageUrl']=RecordfileModel::getImgMrl($rec['path']).'?'.Ouuid();   //$rec['path']为图片文件的URL路径
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
		$rec=array('owner'=>0,'channelid'=>0,'length'=>'','createtime'=>'','name'=>'','descript'=>'','seq'=>0);
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
				if(false===$rt) throw new Exception('更新失败，请通知管理员。'.$dbRf->getLastSql());
			}
			
		}catch (Exception $ex){
			Oajax::errorReturn($ex->getMessage());
			return;
		}
//dump($rec);
		Oajax::successReturn();
		return;		
	}
	
	
	
	/**
     * 删除录像记录及文件
     * 前端会POST整个录像文件记录,以及以下权限标识：
     *  - 上传（新建）录像文件,共享录像到其它频道权限：'permitCreate'=[true|false]
     *  - 修改、删除录像记录权限'permitModify' = [true|false]
     *  - 只能操作自己的记录 viewSelf=[true|false]
     * 参数$id,$path必须提供
     * @param $id int   记录id
     * @param path string 录像文件相对路径及名称
     *
     */
	public function deleteAjax($id,$path){
		//$path='/000/020/173/1.mp4';
		$dbRf=D('recordfile');
		$id=intval($id);
		try{
		    if(!contextToken::verifyToken(self::VODACTION_TOKEN, $_POST[self::VODACTION_TOKEN])) throw new Exception("非法访问！");
			if(1>$id) throw new Exception('参数错误，丢失频道ID！');
			if(intval($_POST['channelid'])!=0) throw new Exception("请先取消与频道的关联后再删除。");

			//查看online表中是否有与将被删除资源的记录（有未结算的记录）
            $dbOnline=D('online');
            $conut=$dbOnline->where(array("objtype"=>"vod","refid"=>$id))->count();
            if($conut>0) throw new Exception("该资源还要未完成结算的点播记录，请1小时后再试。");

			//$rt=$dbRf->where(array('id'=>$id,'size'=>0))->delete(); //只能删除新建而没有录像文件的记录。这些记录保证没有消费，因消费结算时需要读录像记录。
            $dbRf->remove($id); //删除记录。

			//删除主记录时同时视频文件及图片，共享生成的记录只删除数据表记录不删除录像文件
			if(intval($_POST['sourceid'])<1){
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
        set_time_limit(7200);
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

        $vodname=basename($_REQUEST['path']);   //path参数包括 图片文件的相对路径及文件名
        $vodname=substr($vodname,0,strrpos($vodname,'.'));    //剔除文件扩展名
        $vodpath=RecordfileModel::getImgPhysicalPath(dirname($_REQUEST['path']));
		if($_REQUEST['type']=='cover') $ext='jpg';
		else $ext='mp4';
		$vodname =$vodname.'.'.$ext;
logfile('Upload path:'.$vodpath.' name:'.$vodname,LogLevel::INFO);
		
		// This will retrieve the "intended" request method.  Normally, this is the
		// actual method of the request.  Sometimes, though, the intended request method
		// must be hidden in the parameters of the request.  For example, when attempting to
		// delete a file using a POST request. In that case, "DELETE" will be sent along with
		// the request in a "_method" parameter.
		
logfile(json_encode2($_REQUEST),LogLevel::DEBUG);
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
        $filePath=$_POST['path'];
        $filePath=$dbRf->setCover($_POST['recordId'],$filePath);
		$arr=array();
		$arr['imgUrl']=RecordfileModel::getImgMrl($filePath);
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
		$filePath=$_POST['path'];
		$fileSize=$dbRf->updateVideoSize($_POST['recordId'],$filePath); //输入原文件相对路径及名称，输出新的相对路径及名称
logfile('recordId='.$_REQUEST['recordId'].' size='.$fileSize,LogLevel::DEBUG);
		$arr=array();
		$arr['fileSize']=$fileSize;
		$arr['path']=$filePath;
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

    /**
     * 共享一个录像记录到其它频道。
     * 前端POST源录像记录以及以下参数：
     *  target: int 目标频道ID
     * 有关权限参数：
     *  - 上传（新建）录像文件,共享录像到其它频道权限：'permitCreate'=[true|false]
     *  - 修改、删除录像记录权限'permitModify' = [true|false]
     *  - 只能操作自己的记录 viewSelf=[true|false]
     * 若viewSelf==true只能共享到属主为自己的频道
     */
	public function shareAjax(){
	    $sourceId=intval($_POST['id']); //源录像记录ID
        $targetChannel=intval($_POST['target']);    //目标频道ID
//var_dump($_POST['viewSelf'],$_POST['viewSelf']=="false");
        $viewSelf=($_POST['viewSelf']=="false")?false:true;   //只能操作自己录像记录标识
        try{
            if(empty($sourceId) || empty($targetChannel)) throw new Exception("缺少必要的参数。");
            if(intval($_POST['sourceid'])>0) throw new Exception("分享的记录不能再次分享。");
            $dbRf=D('recordfile');
            $dbChannel=D("channel");
            $targetOwner=$dbChannel->where("id=$targetChannel")->getField("owner");
//var_dump($targetOwner);
//var_dump($viewSelf,$this->userId());
            if(null==$targetOwner) throw new Exception("找不到你需要的频道:".$targetChannel);
            if($viewSelf && ($this->userId() !=$targetOwner)) throw new Exception("您没权操作其它播主的录像。");
            $newRecord=array(
                "createtime"=>$_POST['createtime'],
                "owner"=>$targetOwner,
                "channelid"=>$targetChannel,
                "size"=>$_POST['size'],
                "length"=>$_POST['length'],
                "path"=>$_POST["path"],
                "name"=>$_POST["name"],
                "descript"=>$_POST["descript"],
                "sourceid"=>$sourceId
            );
            $recId=$dbRf->add($newRecord);
            if(false==$recId) throw new Exception("共享失败，请检查目标频道是否已经有了此录像。");
            //更新分享值，不保证成功
            $dbRf->where("id=".$sourceId)->setDec("sourceid");
            echo "共享成功，新录像记录ID：".$recId;
        }catch (Exception $e){
            echo $e->getMessage();
            return;
        }

    }

    public function downloadFile(){
	    //echo "download";
        //dump($_POST);
	    try{
            if(!contextToken::verifyToken(self::VODACTION_TOKEN, $_POST[self::VODACTION_TOKEN])) throw new Exception("非法访问！");

            $path=$_POST["path"];
            $basePath=(''==C(vodfile_base_path))?'/vodfile':C(vodfile_base_path);
            $filePath=$_SERVER["DOCUMENT_ROOT"].$basePath.$path;

            $fancyName=$_POST["channelid"]."_".uniqid();
            $fancyName .=".".pathinfo($path,PATHINFO_EXTENSION);
//var_dump($path,$fancyName); return;
            $speedLimit=0;//1024*1024;  //限速1M Byte/S
            //$filePath="D:/abc.mp4";
            //$fancyName="";
            Ou_downloadFile($filePath,$fancyName,true,$speedLimit);
        }catch (Exception $e){
	        echo $e->getMessage();
        }
        //Ou_downloadFile("D:/abc.mp4",'',true,100000);
    }
}

/**
 * 下载文件支持断点续传
 * @param string $fileName     要下载的文件及路径
 * @param string $fancyName    在客户端写入的默认文件名
 * @param bool $forceDownload   默认true,强制浏览器下载而不是在浏览器中打开
 * @param int $speedLimit       限速Byte/S, 0-不限
 * @param string $contentType
 * @return bool
 */
function Ou_downloadFile($fileName, $fancyName = '', $forceDownload = true, $speedLimit = 0, $contentType = '') {
    //必须是文件且可读才能下载
    ob_clean();
    if (!is_file($fileName) || !is_readable($fileName))   {
        //header("HTTP/1.1 404 Not Found");
        echo "找不到要下载的文件。";
        return false;
    }
    ignore_user_abort(false);

    $fileStat = stat($fileName);
    $lastModified = $fileStat['mtime']; //上次修改时间Unix时间戳

    $md5 = md5($fileStat['mtime'] .'='. $fileStat['ino'] .'='. $fileStat['size']);
    $etag = '"' . $md5 . '-' . crc32($md5) . '"';

    header('Last-Modified: ' . gmdate("D, d M Y H:i:s", $lastModified) . ' GMT');
    header("ETag: $etag");

    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $lastModified)  {
        header("HTTP/1.1 304 Not Modified");
        return true;
    }

    if (isset($_SERVER['HTTP_IF_UNMODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_UNMODIFIED_SINCE']) < $lastModified)   {
        header("HTTP/1.1 304 Not Modified");
        return true;
    }

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) &&  $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)   {
        header("HTTP/1.1 304 Not Modified");
        return true;
    }

    if ($fancyName == '')  {
        $fancyName = basename($fileName);
    }

    if ($contentType == '')   {
        $contentType = 'application/octet-stream';
    }

    $fileSize = $fileStat['size'];

    $contentLength = $fileSize;
    $isPartial = false;

    if (isset($_SERVER['HTTP_RANGE']))
    {
        if (preg_match('/^bytes=(\d*)-(\d*)$/', $_SERVER['HTTP_RANGE'], $matches))
        {
            $startPos = $matches[1];
            $endPos = $matches[2];

            if ($startPos == '' && $endPos == '')
            {
                return false;
            }

            if ($startPos == '')
            {
                $startPos = $fileSize - $endPos;
                $endPos = $fileSize - 1;
            }
            else if ($endPos == '')
            {
                $endPos = $fileSize - 1;
            }

            $startPos = $startPos < 0 ? 0 : $startPos;
            $endPos = $endPos > $fileSize - 1 ? $fileSize - 1 : $endPos;

            $length = $endPos - $startPos + 1;

            if ($length < 0)
            {
                return false;
            }

            $contentLength = $length;
            $isPartial = true;
        }
    }

// send headers
    if ($isPartial)
    {
        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes $startPos-$endPos/$fileSize");

    }
    else
    {
        header("HTTP/1.1 200 OK");
        $startPos = 0;
        $endPos = $contentLength - 1;
    }

    header('Pragma: cache');
    header('Cache-Control: public, must-revalidate, max-age=0');
    header('Accept-Ranges: bytes');
    header('Content-type: ' . $contentType);
    header('Content-Length: ' . $contentLength);

    if ($forceDownload)
    {
        header('Content-Disposition: attachment; filename="' . rawurlencode($fancyName). '"');
    }

    header("Content-Transfer-Encoding: binary");

    $bufferSize = 4096;

    if ($speedLimit != 0)    {
        $packetTime = floor($bufferSize * 1000000 / $speedLimit);
    }

    $bytesSent = 0;
    $fp = fopen($fileName, "rb");
    fseek($fp, $startPos);
    while ($bytesSent < $contentLength && !feof($fp) && connection_status() == 0 )
    {
        if ($speedLimit != 0)
        {
            list($usec, $sec) = explode(" ", microtime());
            $outputTimeStart = ((float)$usec + (float)$sec);
        }

        $readBufferSize = $contentLength - $bytesSent < $bufferSize ? $contentLength - $bytesSent : $bufferSize;
        $buffer = fread($fp, $readBufferSize);

        echo $buffer;

        ob_flush();
        flush();

        $bytesSent += $readBufferSize;

        if ($speedLimit != 0)
        {
            list($usec, $sec) = explode(" ", microtime());
            $outputTimeEnd = ((float)$usec + (float)$sec);

            $useTime = ((float) $outputTimeEnd - (float) $outputTimeStart) * 1000000;   //发送使用的微妙数
            $sleepTime = round($packetTime - $useTime);
            if ($sleepTime > 0)
            {
                usleep($sleepTime);
            }
        }
    }
    return true;
}
?>