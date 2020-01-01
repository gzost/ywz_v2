<?php
import('ORG.Util.Image');
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once(APP_PATH.'/Common/functions.php');
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/GoodsModel.php');
require_once(LIB_PATH.'Model/ChannelreluserModel.php');
require_once APP_PATH.'../public/CommonFun.php';
require_once APP_PATH.'../public/FileUpload.Class.php';
require_once(APP_PATH.'/Common/functions.php');

require_once APP_PATH.'../public/uploadhandler.php';
require_once APP_PATH.'../public/Imgcompress.php';
class ChannelAction extends AdminBaseAction
{
	protected $chnCond = array('name'=>'', 'anchor'=>'');
	protected $recordCond = array('name'=>'');
	//protected $author = null;	//授权对象
	protected $webVar = array();
	protected $condBase = array('chnId'=>'', 'name'=>'', 'descript'=>'', 'type'=>'', 'status'=>'', 'adpush'=>'', 'discuss'=>'', 'mp4RecLink'=>'', 'wxonly'=>false, 'openApiKey'=>'', 'openAgentUser'=>'', 'playtype'=>'', 'streamkey'=>'', 'avright'=>false, 'showposter'=>false);
	protected $condAdmin = array('chnId'=>'', 'serno'=>'', 'push'=>'', 'rtmp'=>'', 'hls'=>'', 'anchorAccount'=>'', 'credit'=>'0');
	protected $condSign = array('chnId'=>'', 'quest'=>'', 'questAns'=>'', 'signpass'=>false);
	protected $condAd = array('chnId'=>'');

	function __construct(){
		parent::__construct();

	}

	public function EditBaseDemo()
	{
		$this->display();
	}

	public function EditBaseDemoSbumit()
	{
		var_dump($_FILES);
		//echo 'EditBaseDemoSbumit';
		//$this->redirect('EditBaseDemo');
	}

	public function AssignPage($type='')
	{
		//$menu=new AdminMenu();
		$this->baseAssign();
		if('list' === $type)
		{
			//$menuStr=$menu->Menu(1);
			$this->assign('rand', time());
			$this->assign('keyname', session('_chnClistKey'));
			$this->assign('keyanchor', session('_chnClistKeyAnchor'));			
			//$this->assign('menuStr', $menuStr);
			$this->assign('mainTitle', '频道列表');
		}
		else if('edit' === $type)
		{
			//$menuStr=$menu->Menu(1);
			$this->assign('rand', time());
			//$this->assign('menuStr', $menuStr);
			$this->assign('mainTitle', '频道属性');
		}
		else if('record' === $type)
		{
			//$menuStr=$menu->Menu(1);
			$this->assign('rand', time());
			$this->assign('keyname', session('_recordClistKey'));
			//$this->assign('menuStr', $menuStr);
			$this->assign('mainTitle', '频道录像');
		}
		//$this->assign('userName',$this->userName());
	}

	public function ImgThumb($pic, $thumb)
	{
		$image = new Image();
		$image->thumb2($pic, $thumb, '', 50, 50);
	}

	public function SignUpAnswer()
	{
		$chnId = session('_signChnId');
		session('_signChnId', null);
		$userInfo=$this->author->getUserInfo();

		$chnDal = new ChannelModel();
		$questList = $chnDal->GetQuestArray($chnId);
		$attr = $chnDal->GetAttrArray($chnId);
		//是否自动审核
		$signpass = $attr['signpass'];

		$ansList = array();
		foreach($questList as $i => $r){
			$ansList[] = array('quest' => $r, 'answer' => I('POST.ans'.$i, ''));
		}

		$relInfo = array();
		$relInfo['chnid'] = $chnId;
		$relInfo['uid'] = $userInfo['userId'];
		$relInfo['note'] = json_encode($ansList);
		$relInfo['status'] = '禁用';
		$relInfo['begindate']=date('Y-m-d H:i:s');
		$relInfo['enddate']='6999-12-31';
		$isPass = false;
		if($signpass)
		{
			//进行审核
			$isPass = true;
			$ind = 0;
			foreach($ansList as $i => $item)
			{
				if(0 < strlen($attr['signQuestAns'][$ind]) && 0 <> strcmp($attr['signQuestAns'][$ind], $item['answer']))
				{
					$isPass = false;
				}
				$ind++;
			}
			if($isPass)
			{
				$relInfo['status'] = '正常';
			}
		}

		$relDal = D('Channelreluser');
		$ret = $relDal->add($relInfo);
		if($signpass && $isPass)
		{
			$this->redirect('HDPlayer/play', array('chnId'=>$chnId));
		}
		else
		{
			$this->SignUpMsg("已成功报名，请耐心等待主播的审核。", $chnId);
		}
		//echo $relDal->getLastSQL();
	}

	public function SignUpMsg($msg = '', $chnId = 0)
	{
		$chnDal = new ChannelModel();
		$attr = $chnDal->getAttrArray($chnId);

		$infoImg = $attr['infoImg'];
		$isShowInfoImg = false;
		if(0 < strlen($infoImg))
		{
			$isShowInfoImg = true;
		}

		session('_signChnId', $chnId);
		$this->assign('infoImg', $this->GetWebPath($chnId).$infoImg);
		$this->assign('isShowInfoImg', $isShowInfoImg);

		$this->assign('message', $msg);
		$this->display('SignUpMsg');
		exit;
	}

	//报名页面
	public function SignUp($chnId = 0)
	{
		$this->author = new authorize();
		$chnUser = new ChannelreluserModel();

		$userInfo = $this->author->getUserInfo();
		$st = $chnUser->WhatViewer($chnId,$userInfo['userId']);
//dump($st);die('signUP');
		switch($st)
		{
			case -1:
				//未报名
				break;
			case 0:
				//已报名未通过
				//跳转
				$this->SignUpMsg("已成功报名，请耐心等待主播的审核。", $chnId);
				break;
			case 1:
				//可以收看
				//跳转
				$this->redirect('HDPlayer/play', array('chnId'=>$chnId));
				break;
		}

		$chnDal = new ChannelModel();
		$attr = $chnDal->getAttrArray($chnId);

		$questList = $attr['signQuest'];
		$queListOut = array();

		foreach($questList as $i => $r)
		{
			if(0 < strlen($r))
			{
				$queListOut[] = array('index'=>$i, 'quest'=>$r);
			}
		}

		/*
		$questList = array();
		$questList[] = array('quest'=>'请问你的姓名？');
		$questList[] = array('quest'=>'请问你的性别？');
		$questList[] = array('quest'=>'请问你的身高？');
		$t = json_encode($questList);
		echo $t;
		exit;
		*/
		$infoImg = $attr['infoImg'];
		$isShowInfoImg = false;
		if(0 < strlen($infoImg))
		{
			$isShowInfoImg = true;
		}

		session('_signChnId', $chnId);
		$this->assign('infoImg', $this->GetWebPath($chnId).$infoImg);
		$this->assign('isShowInfoImg', $isShowInfoImg);
		$this->assign('answerUrl', U('SignUpAnswer'));
		$this->assign('questList', $queListOut);
		$this->display();
	}

	public function CList()
	{
		pagination::clear('chnSearch');
		//condition::clear('chnSearch');
		$isAdmin = $this->IsAdminRole();


		$this->assign('chnAddUrl', U('AddNew'));
		$this->assign('editUrl', U('Edit'));
		$this->assign('userRefUrl', U('Subscriber/authorize'));
		$this->AssignPage('list');
		$this->assign($this->webVar);
		$this->display();
	}

	public function CListUpdate($id = 0, $adpush = 0)
	{
		$chnDal = new ChannelModel();
		$chnDal->where(array('id'=>$id))->save(array('adpush'=>$adpush));
		echo '{"success":true}';
	}

	public function CListDelete($id = 0)
	{
		$chnDal = new ChannelModel();
		$chnDal->where(array('id'=>$id))->delete();
		echo '{"success":true}';
	}

	public function AddNew()
	{
		$chnDal = new ChannelModel();
		$newId = $chnDal->AddNew();
		$this->Edit($newId);
		//return;
		//$this->redirect('Edit', array('chnId', $newId));
	}

	public function chnSearch()
	{
		$this->chnSearchData();

		/*
		$this->assign('editUrl', U('Edit'));
		$this->assign('userRefUrl', U('Subscriber/authorize'));
		$this->AssignPage('list');
		$this->display('CList');
		*/
		$this->redirect('CList');
	}

	public function chnSearchData($act='')
	{
		if('' === $act)
		{
			$this->chnCond['name'] = I('post.keyname', '');
			$this->chnCond['anchor'] = I('post.keyanchor', '');
			if(0 === strlen($this->chnCond['name']))
			{
				session('_chnClistKey', null);
			}
			else
			{
				session('_chnClistKey', $this->chnCond['name']);
			}
			if(0 === strlen($this->chnCond['anchor']))
			{
				session('_chnClistKeyAnchor', null);
			}
			else
			{
				session('_chnClistKeyAnchor', $this->chnCond['anchor']);
			}
			condition::save($this->chnCond, 'chnSearch');	//更新并存储最新的查询条件
		}
		else
		{
			$this->chnCond = condition::get('chnSearch');
			$this->chnCond = arrayZip($this->chnCond,array(null,0,'不限','0','','全部'));
		}


		$w = array();
		$w2 = array();
		$isAdmin = $this->isOpPermit('F');
		$userInfo = authorize::getUserInfo();

		//if(!$isAdmin)
		{
			//$w['anchor'] = $userInfo['userId'];
		}

		$chnDal = D('channel');
		if(!empty($this->chnCond['name']))
		{
			$w['name'] = array('like', '%'.$this->chnCond['name'].'%');
		}
		if(!empty($this->chnCond['anchor']))
		{
			$w2['username'] = array('like', '%'.$this->chnCond['anchor'].'%', 'OR');
			$w2['account'] = array('like', '%'.$this->chnCond['anchor'].'%', 'OR');
			$w2['_logic'] = 'or';
			$w['_complex'] = $w2;
		}
		$data = $chnDal->field('a.id, a.name,a.type, a.status, adpush, concat(account,\'(\',username,\')\') as ownername,S.name as streamname')->alias('a')->where($w)
				->join('__USER__ u on owner = u.id')->join("__STREAM__ S on S.id=a.streamid")->select();
		//echo $chnDal->getLastSql();
		pagination::setData('chnSearch', $data);
	}

	public function UserRef()
	{
		$menu=new AdminMenu();
		$menuStr=$menu->Menu(1);
 		$this->assign('menuStr',$menuStr);
 		$this->assign('mainTitle','观众管理');
		$this->display();
	}

	public function UserData()
	{
		$chnDal = D('channel');
		$data = $chnDal->select();
		Data2ListJson($data);
	}

	public function RecordListData($page=1,$rows=1)
	{
		if(!pagination::isAvailable('recordSearch'))
		{
			$this->recordSearchData('redo');
		}

		$data=pagination::getData('recordSearch',$page,$rows);
		Data2ListJson($data, $rows);
	}

	public function recordSearchData($act='')
	{
		if('' === $act)
		{
			$this->recordCond['name'] = I('post.keyname', '');
			if(0 === strlen($this->recordCond['name']))
			{
				session('_recordClistKey', null);
			}
			else
			{
				session('_recordClistKey', $this->recordCond['name']);
			}
			condition::save($this->recordCond, 'recordSearch');	//更新并存储最新的查询条件
		}
		else
		{
			$this->recordCond = condition::get('recordSearch');
		}

		$w = array();

		$recDal = D('channelrecord');
		if(!empty($this->recordCond['name']))
		{
			//$w['name'] = array('like', '%'.$this->recordCond['name'].'%');
			$w['stream'] = array('like', '%'.$this->recordCond['name'].'%');
		}

		//$data = $recDal->field('c.name, __PREFIX__channelrecord.stream, endtimestr,concat("http://58.67.171.55:8008/",recordfile) as recordurl')->where($w)->join('__PREFIX__channel c on c.stream = __PREFIX__channelrecord.stream', 'left')->select();

		$data = $recDal->field('stream, endtimestr, concat("http://58.67.171.55:8008/",recordfile) as recordurl')->where($w)->order('id desc')->select();

		pagination::setData('recordSearch', $data);
	}

	public function ListData($page=1,$rows=1)
	{
		//if(!pagination::isAvailable('chnSearch'))
		{
			$this->chnSearchData('redo');
		}
		
		$data=pagination::getData('chnSearch',$page,$rows);
		Data2ListJson($data, $rows);
	}

	public function EditAdminSubmit()
	{
		$isAdmin = $this->IsAdminRole();
		if(!$isAdmin)
		{
			//没有
			return;
		}
		$post = GetPostVar($this->condAdmin);
		$chnId = $post['chnId'];
		$userInfo = authorize::getUserInfo();

		$chnDal = D('channel');
		$chnInfo = $chnDal->where(array('id'=>$chnId))->find();

		if($isAdmin)
		{
			$chnDal = new channelModel();
			$attr = $chnDal->getAttrArray($chnId);

			$save = array();

			//如果有输入用户帐号才更新主播关联
			if(0 < strlen($post['anchorAccount']))
			{
				//var_dump($post['anchorAccount']);
				$userDal = new UserModel();
				$newId = $userDal->getUserId($post['anchorAccount']);
				//echo $userDal->getLastSQL();
				//var_dump($netId);
				if(0 < $newId)
				{
					$save['anchor'] = $newId;
				}				 
				unset($post['anchorAccount']);
			}
			$save['credit'] = $post['credit'];
			$attr = array_merge($attr, $post);
			$save['attr'] = json_encode($attr);
			$ret = $chnDal->where(array('id'=>$chnId))->save($save);

			$this->EditAdmin($chnId);
		}
		else
		{
			$this->webVar['errMsg'] = '非法操作！';
			$this->EditAdmin($chnId);
			exit;
		}
	}

	public function EditAdmin($chnId = 0)
	{
		$isAdmin = $this->IsAdminRole();

		$chnDal = new channelModel();
		$chnInfo = $chnDal->where(array('id'=>$chnId))->find();
		$attr = $chnDal->getAttrArray($chnId);

		$this->webVar['credit'] = $chnInfo['credit'];
		$this->webVar['anchorInfo'] = '(未绑定)';
		$this->webVar['chnId'] = $chnId;
		$this->webVar['stream'] = $chnInfo['stream'];
		if(0 < $chnInfo['anchor'])
		{
			$userDal = D('user');
			$row = $userDal->where(array('id'=>$chnInfo['anchor']))->find();
			$this->webVar['anchorInfo'] = $row['username'].'('.$row['account'].')';
		}

		if($isAdmin)
		{
			$this->assignB($this->webVar);
			$this->assignB($attr);
		}

		$this->display('EditAdmin');
	}

	public function EditAd($chnId = 0)
	{
		$isAdmin = $this->IsAdminRole();
		$userInfo = authorize::getUserInfo();

		$chnDal = new channelModel();
		$chnInfo = $chnDal->where(array('id'=>$chnId))->find();
		$attr = $chnDal->getAttrArray($chnId);

		//var_dump($attr['adImg']);

		if($isAdmin || $chnInfo['anchor'] === $userInfo['userId'])
		{
			$this->webVar['adUp'] = U('EditAdUp');
			$this->webVar['adDown'] = U('EditAdDown');
			$this->webVar['adDel'] = U('EditAdDel');
			$this->webVar['chnId'] = $chnId;
			$this->webVar[''] = U('EditAdList', array('chnId'=>$chnId));

			$this->assignB($this->webVar);
			$this->assignB($attr);

			//获取列表并缓存
			$this->EditAdGetData($chnId, $attr);
		}

		$this->display('EditAd');
	}

	public function EditAdDel($chnId = 0, $id = -1)
	{
		if(0 < $id)
		{
			$chnDal = new channelModel();
			$attr = $chnDal->getAttrArray($chnId);

			//删除文件
			unlink($this->GetSavePath($chnId).$attr['adImg'][$id]);
			//删除记录
			//unset($attr['adImg'][$id]);
			array_splice($attr['adImg'], $id, 1);

			$save['attr'] = json_encode($attr);

			$chnDal->where(array('id'=>$chnId))->save($save);
		}

		$this->EditAd($chnId);
	}

	public function EditAdUp($chnId = 0, $id = -1)
	{
		if(0 < $id)
		{
			$chnDal = new channelModel();
			$attr = $chnDal->getAttrArray($chnId);

			$tmp = $attr['adImg'][$id];
			$attr['adImg'][$id] = $attr['adImg'][$id-1];
			$attr['adImg'][$id-1] = $tmp;

			$save['attr'] = json_encode($attr);

			$chnDal->where(array('id'=>$chnId))->save($save);
		}

		$this->EditAd($chnId);
	}

	public function EditAdDown($chnId = 0, $id = -1)
	{
		$chnDal = new channelModel();
		$attr = $chnDal->getAttrArray($chnId);

		if( isset($attr['adImg'][$id+1]) )
		{
			$tmp = $attr['adImg'][$id];
			$attr['adImg'][$id] = $attr['adImg'][$id+1];
			$attr['adImg'][$id+1] = $tmp;

			$save['attr'] = json_encode($attr);

			$ret = $chnDal->where(array('id'=>$chnId))->save($save);
		}

		$this->EditAd($chnId);
	}

	public function EditAdGetData($chnId = 0, $attr = null)
	{
		if(null === $attr)
		{
			$chnDal = new channelModel();
			$attr = $chnDal->getAttrArray($chnId);
		}
		$adImg = array();
		foreach($attr['adImg'] as $i => $v)
		{
			$adImg[] = array('name'=>$v, 'id'=>$i);
		}
		//获取列表并缓存
		pagination::setData('chnEditAd', $adImg);
	}

	public function EditAdList($chnId = 0, $page=1, $rows=1)
	{
		if(!pagination::isAvailable('chnEditAd'))
		{
			$this->EditAdGetData($chnId);
		}
		
		$data=pagination::getData('chnEditAd',$page,$rows);
		Data2ListJson($data, $rows);
	}

	public function EditAdSubmit()
	{
		$isAdmin = $this->IsAdminRole();

		$post = GetPostVar($this->condAd);
		$chnId = $post['chnId'];
		$userInfo = authorize::getUserInfo();

		$chnDal = D('channel');
		$chnInfo = $chnDal->where(array('id'=>$chnId))->find();

		if($isAdmin || $chnInfo['anchor'] === $userInfo['userId'])
		{
			$chnDal = new channelModel();
			$attr = $chnDal->getAttrArray($chnId);

			//处理上传文件
			$upload = new FileUpload();
			$uparray = $upload->BeginUpload('importFile', array('jpg','png'), 1024*1024);
			if(0 < $uparray[0]['error'])
			{
				$this->webVar['errMsg'] = $uparray[0]['errorMsg'];
				$this->EditAd($chnId);
				exit;
			}
			else if(0 < $uparray[0]['error'])
			{
				$this->webVar['errMsg'] = $uparray[0]['errorMsg'];
				$this->EditAd($chnId);
				exit;
			}
			else if(false === $uparray[0]['extPass'])
			{
				$upload->Cancel($uparray);
				$this->webVar['errMsg'] = '不允许上传这种文件类型!';
				$this->EditAd($chnId);
				exit;
			}
			else if(false === $uparray[0]['sizePass'])
			{
				$upload->Cancel($uparray);
				$this->webVar['errMsg'] = '文件过大,不允许上传!';
				$this->EditAd($chnId);
				exit;
			}
			else
			{
				//合法
				$upTargDir = $this->GetSavePath($chnId);
				$filename = GenTimeFileName(4).'.'.$uparray[0]['ext'];
				$ret = move_uploaded_file($uparray[0]['tmp_name'], $upTargDir.$filename);
				if(false === $ret)
				{
					$this->webVar['errMsg'] = '文件保存失败!';
					$this->EditAd($chnId);
					exit;
				}
				chmod($upTargDir.$filename, 0660);

				$post['adImg'] = $filename;
			}

			//填写更新数据
			$attr['adImg'][] = $post['adImg'];

			$save['attr'] = json_encode($attr);
			$ret = $chnDal->where(array('id'=>$chnId))->save($save);

			//echo $chnDal->getLastSQL();
			$this->EditAd($chnId);
		}
		else
		{
			$this->webVar['errMsg'] = '非法操作！';
			$this->EditAd($chnId);
			exit;
		}
	}



	public function EditSignSubmit()
	{
		$isAdmin = $this->IsAdminRole();

		$post = GetPostVar($this->condSign);
		$chnId = $post['chnId'];
		$userInfo = authorize::getUserInfo();

		$chnDal = D('channel');
		$chnInfo = $chnDal->where(array('id'=>$chnId))->find();

		if($isAdmin || $chnInfo['anchor'] === $userInfo['userId'])
		{
			$chnDal = new channelModel();
			$attr = $chnDal->getAttrArray($chnId);

			$attr['signQuest'] = $post['quest'];
			$attr['signQuestAns'] = $post['questAns'];
			$attr['signpass'] = $post['signpass'];
			

			$save['attr'] = json_encode2($attr);
			$ret = $chnDal->where(array('id'=>$chnId))->save($save);

			//echo $chnDal->getLastSQL();
			$this->EditSign($chnId);
		}
		else
		{
			$this->webVar['errMsg'] = '非法操作！';
			$this->EditSign($chnId);
			exit;
		}
	}

	public function EditSign($chnId = 0)
	{
		$isAdmin = $this->isAdmin;		//IsAdminRole();
//var_dump($isAdmin);
		$userInfo = authorize::getUserInfo();
//var_dump($userInfo);
		$chnDal = new channelModel();
		$chnInfo = $chnDal->where(array('id'=>$chnId))->find();

		$attr = $chnDal->getAttrArray($chnId);
		/*
		$attr['signQuest'][0]['quest'] = 'q1';
		$attr['signQuest'][1]['quest'] = 'q2';
		$attr['signQuest'][2]['quest'] = 'q3';
		var_dump($attr['signQuest']);
		*/
        $this->baseAssign();
		if($isAdmin || $chnInfo['anchor'] === $userInfo['userId'])
		{
			$this->webVar['chnId'] = $chnId;
			$this->webVar['signQuest'] = $attr['signQuest'];
			$this->webVar['signQuestAns'] = $attr['signQuestAns'];
			$this->webVar['signpass'] = $attr['signpass'];

			$this->assignB($this->webVar);
		}

		$this->display('EditSign');
	}

	public function EditBaseDelRec($chnId)
	{
		$isAdmin = $this->IsAdminRole();
		$userInfo = authorize::getUserInfo();

		$chnDal = new channelModel();
		$chnInfo = $chnDal->where(array('id' => $chnId))->find();

		if($isAdmin || $chnInfo['anchor'] === $userInfo['userId'])
		{
			$attr = json_decode($chnInfo['attr'], true);
			//删除文件
			unlink($this->GetSavePath($chnId).$attr['mp4Rec']);
			$attr['mp4Rec'] = null;
		}

		$this->EditBase($chnId);
	}

	public function EditBaseClearDis($chnId)
	{
		//$isAdmin = $this->IsAdminRole();
		$userInfo = authorize::getUserInfo();

		$chnDal = new channelModel();
		$chnInfo = $chnDal->where(array('id' => $chnId))->find();

		//if($isAdmin || $chnInfo['anchor'] === $userInfo['userId'])
		{
			$chatDal = D('webchat');
			$chatDal->where(array('chnid'=>$chnId))->delete();
		}

		echo '{"result":"true"}';
	}

	public function EditBase($chnId = 0, $act = '')
	{
		if('submit' === $act)
		{
			$this->EditBaseSubmit();
			exit;
		}

		$isAdmin = $this->IsAdminRole();
		$userInfo = authorize::getUserInfo();

		$chnDal = new channelModel();
		$chnInfo = $chnDal->where(array('id' => $chnId))->find();
		if(null != $chnInfo['attr'])
		{
			$attr = json_decode($chnInfo['attr'], true);
			if(null != $attr)
			{
				$chnInfo = array_merge($attr, $chnInfo);
			}
		}

		if($isAdmin || $chnInfo['anchor'] === $userInfo['userId'])
		{
			$this->webVar['token'] = session('chnEditToken');
			$this->webVar['chnId'] = $chnId;

			$path = $this->GetWebPath($chnId);
			$chnInfo['infoImg'] = $path.$chnInfo['infoImg'];
			$chnInfo['infoImg48'] = $path.$chnInfo['infoImg48'];
			if(0 < strlen($chnInfo['mp4RecLink']))
			{
				$chnInfo['mp4Rec'] = $chnInfo['mp4RecLink'];
			}
			else
			{
				$chnInfo['mp4Rec'] = $path.$chnInfo['mp4Rec'];
			}

			if(!empty($chnInfo['openAgentUser']))
			{
				$userDal = D('user');
				$row = $userDal->where(array('account'=>$chnInfo['openAgentUser']))->find();
				$this->webVar['openAgentName'] = $row['username'];//.'('.$row['account'].')';
			}

			$this->assignB($this->webVar);
			$this->assignB($chnInfo);
		}
		$accUrl = 'http://'.$_SERVER['HTTP_HOST'].'/r.php?i='.$chnId;
		$this->assign('qrUrl', 'http://micxp1.duapp.com/qr.php?value='.urlencode($accUrl));
		$this->assign('accUrl', $accUrl);
		$this->assign('playtype', $chnInfo['playtype']);
		$this->assign('EditBaseDelRec', U('EditBaseDelRec'));
		$this->assign('EditBaseClearDis', U('EditBaseClearDis'));
		$this->display('EditBase');
	}

	public function FileMoveBat()
	{
		$chnDal = D('channel');
		$ids = $chnDal->field('id')->select();
		foreach($ids as $i => $v)
		{
			$this->FileMove($v['id']);
		}
	}

	public function FileMove($chnId = 0)
	{
		if(0 < $chnId)
		{
			$chnDal = D('channel');
			$attr = $chnDal->getAttrArray($chnId);
			$path = $this->GetSavePath($chnId);
			//封面图片
			if(!file_exists($path.$attr['infoImg']))
			{
				copy(C('roomImgUpload').$attr['infoImg'], $path.$attr['infoImg']);
			}

			//录像文件
			if(!file_exists($path.$attr['mp4Rec']))
			{
				copy(C('roomImgUpload').$attr['mp4Rec'], $path.$attr['mp4Rec']);
			}

			//广告图片

		}
	}

	public function SetMod()
	{
		echo 'set begin';
		$dir = '/var/www/ywz/webroot/room/000/00';
		$ret = unlink($dir);
		//$ret = chmod($dir, 0777);
		//var_dump($ret);
		//$ret = chmod(C('roomImgUpload').'10/', 0770);
		//var_dump($ret);
		//echo 'end';
	}

	public function GetChnFload($chnId = '', $isCheck = true)
	{
		$int_chnid=intval($chnId);
		if($int_chnid<1 || strlen($int_chnid)!=strlen($chnId)) logfile("BAD chnId:".$chnId,LogLevel::ALERT);
		$fd1 = '000/';
		$fd2 = '00/';
		$len = strlen($chnId);
		if(2 >= $len && 0 < $len)
		{
			$fd2 = $chnId.'/';
		}
		if(2 < strlen($chnId))
		{
			$fd1 = substr($chnId, 0, -2).'/';
			$fd2 = substr($chnId, -2).'/';
		}

		$fd1 = str_pad($fd1,4,0,STR_PAD_LEFT);
		$fd2 = str_pad($fd2,3,0,STR_PAD_LEFT);

		if($isCheck)
		{
			if(!is_dir(C('roomImgUpload').$fd1))
			{
				mkdir(C('roomImgUpload').$fd1);
				chmod(C('roomImgUpload').$fd1, 0774);
			}
			if(!is_dir(C('roomImgUpload').$fd1.$fd2))
			{
				mkdir(C('roomImgUpload').$fd1.$fd2);
				chmod(C('roomImgUpload').$fd1, 0774);
			}
		}
		$path=C('roomImgUpload').$fd1.$fd2;
		mkdir($path.'/photoOrg/',0774);
		mkdir($path.'/photoL',0774);
        mkdir($path.'/photoM',0774);
		return $fd1.$fd2;
	}

	public function GetSavePath($chnId = '')
	{
		return C('roomImgUpload').$this->GetChnFload($chnId);
	}

	public function GetWebPath($chnId = '')
	{
		return C('roomImgView').$this->GetChnFload($chnId);
	}

	public function GetInfoImgSavePath($chnId = 0)
	{
		$path = $this->GetSavePath($chnId).'info/';
		if(!is_dir($path))
		{
			mkdir($path);
			chmod($path, 0774);
		}
		
		return $path;
	}

	/**
	 * 
	 * 根据数据库中记录的路径转换成实际显示的路径
	 * @param $chnAttr array 频道属性数组。若传入null, 方法内从数据库读取
	 * @param $chnId int 频道ID
	 * @return mixed
	 */
	//
	public function getPosterImgUrl($chnAttr = null, $chnId = 0 )
	{
		$attr = $chnAttr;
		if(null == $chnAttr)
		{
			$chnDal = new ChannelModel();
			$attr = $chnDal->getAttrArray($chnId);
		}
		$file = $attr['poster'];
		//var_dump($attr);
		$path = $this->GetSavePath($chnId).$file;
//var_dump($path);
//logfile($path,LogLevel::DEBUG);
		if(is_file($path))
		{
			return $this->GetWebPath($chnId).$file;
		}
		else
		{
			//返回默认图片
			return '/player/default/images/videobg.jpg';
		}
	}

	/**
	 * 
	 * 根据数据库中记录的路径转换成实际显示的路径
	 * $chnAttr 频道属性数组
	 * $chnId 频道ID
	 */
	//
	public function getLogoImgUrl($chnAttr = null, $chnId = 0)
	{
		$attr = $chnAttr;
		if(null == $chnAttr)
		{
			$chnDal = new ChannelModel();
			$attr = $chnDal->getAttrArray($chnId);
		}
		$file = $attr['logo'];
//var_dump($attr);
		$path = $this->GetSavePath($chnId).$file;
//var_dump($path);

		if( is_file($path))
		{
			return $this->GetWebPath($chnId).$file;
		}
		else
		{
			//返回默认图片
			return '/player/default/images/chnlogo.png';
		}
	}



	public function GetInfoImgWebPath($chnId = 0)
	{
		$path = $this->GetWebPath($chnId).'info/';
		return $path;
	}


	public function EditBaseSubmit()
	{
		set_time_limit(0);
		$isAdmin = $this->IsAdminRole();
		$post = GetPostVar($this->condBase);

		$chnId = $post['chnId'];
		$userInfo = authorize::getUserInfo();

		$chnDal = new channelModel();
		$chnInfo = $chnDal->where(array('id'=>$chnId))->find();

		if($isAdmin || $chnInfo['anchor'] === $userInfo['userId'])
		{
			//检验频道名称是否重名
			$dupInfo = $chnDal->field('id, name')->where(array('name'=>$post['name'], 'id'=>array('neq', $chnId)))->find();
			if(null != $dupInfo)
			{
				//重名
				$this->webVar['errMsg'] = '提交的频道名称已存在!';
				$this->EditBase($chnId);
				exit;
			}
			$chnInfo = array_merge($chnInfo, $post);

			//处理属性设置值			
			$attr = $chnDal->getAttrArray($chnId);
			$attr['adpush'] = $post['adpush'];
			$attr['discuss'] = $post['discuss'];
			$attr['mp4RecLink'] = $post['mp4RecLink'];
			$attr['wxonly'] = $post['wxonly'];
			$attr['avright'] = $post['avright'];
			$attr['showposter'] = $post['showposter'];
			$attr['openApiKey'] = $post['openApiKey'];
			if(null != $post['openAgentUser'])
			{
				$attr['openAgentUser'] = $post['openAgentUser'];
			}
			//var_dump($post['openApiKey']);
			if(null != $post['openApiKey'] && 0 < strlen($post['openApiKey']))
			{
				$t = array();
				$t['chnId'] = $chnId;
				$t['keystr'] = $post['openApiKey'];
				$attr['openApiToken'] = EncryEncode($t);
				//var_dump(EncryDecode($attr['openApiToken']));
			}
			else
			{
				$attr['openApiToken'] = '';
			}

			//处理上传文件
			$upload = new FileUpload();
			if(isset($_FILES['importMp4']) && 
				(0 < strlen($_FILES['importMp4']['name']) 
				|| 0 < strlen($_FILES['importMp4']['name'][0])
				))
			{
				//上传录像文件
				$mp4Array = $upload->BeginUpload('importMp4', array('mp4'), 1024*1024*500);
				//var_dump($mp4Array);
				if(0 < $mp4Array[0]['error'])
				{
					$this->webVar['errMsg'] = $uparray[0]['errorMsg'];
					$this->EditAd($chnId);
					exit;
				}
				else if(false === $mp4Array[0]['extPass'])
				{
					$upload->Cancel($mp4Array);
					$this->webVar['errMsg'] = '不允许上传这种录像文件类型';
					$this->EditBase($chnId);
					exit;
				}
				else if(false === $mp4Array[0]['sizePass'])
				{
					$upload->Cancel($mp4Array);
					$this->webVar['errMsg'] = '录像文件过大,不允许上传!';
					$this->EditBase($chnId);
					exit;
				}
				else
				{
						//合法
					$upTargDir = $this->GetSavePath($chnId);
					$filename = GenTimeFileName().'.'.$mp4Array[0]['ext'];
					//var_dump($upTargDir.$filename);
					$ret = move_uploaded_file($mp4Array[0]['tmp_name'], $upTargDir.$filename);
					if(false === $ret)
					{
						$this->webVar['errMsg'] = '录像文件保存失败!';
						$this->EditBase($chnId);
						exit;
					}
					chmod($upTargDir.$filename, 0660);
					//删除旧文件
					//var_dump($attr['mp4Rec']);
					if(0 < strlen($attr['mp4Rec']))
					{
						unlink($upTargDir.$attr['mp4Rec']);
					}
					$attr['mp4Rec'] = $filename;
					//上传了录像文件，需要把录像文件地址删除
					$attr['mp4RecLink'] = '';
					//var_dump($attr['mp4Rec']);
				}				
			}

			if(isset($_FILES['importFile']) && 
				(0 < strlen($_FILES['importFile']['name']) 
				|| 0 < strlen($_FILES['importFile']['name'][0])
				))
			{
				//上传图片
				$uparray = $upload->BeginUpload('importFile', array('jpg','png','gif'), 1024*308);
				if(0 < $uparray[0]['error'])
				{
					$this->webVar['errMsg'] = $uparray[0]['errorMsg'];
					$this->EditAd($chnId);
					exit;
				}
				else if(false === $uparray[0]['extPass'])
				{
					$upload->Cancel($uparray);
					$this->webVar['errMsg'] = '不允许上传这种图片文件类型!';
					$this->EditBase($chnId);
					exit;
				}
				else if(false === $uparray[0]['sizePass'])
				{
					$upload->Cancel($uparray);
					$this->webVar['errMsg'] = '图片文件过大,不允许上传!';
					$this->EditBase($chnId);
					exit;
				}
				else
				{
					//合法
					$upTargDir = $this->GetSavePath($chnId);
					$filename = GenTimeFileName().'.'.$uparray[0]['ext'];

					$ret = move_uploaded_file($uparray[0]['tmp_name'], $upTargDir.$filename);
					if(false === $ret)
					{
						$this->webVar['errMsg'] = '图片文件保存失败!';
						$this->EditBase($chnId);
						exit;
					}
					chmod($upTargDir.$filename, 0660);

					//删除旧文件
					if(0 < strlen($attr['infoImg']))
					{
						unlink($upTargDir.$attr['infoImg']);
					}
					//设置新文件
					$attr['infoImg'] = $filename;
				}
			}

			if(isset($_FILES['infoImg48']) && 
				(0 < strlen($_FILES['infoImg48']['name']) 
				|| 0 < strlen($_FILES['infoImg48']['name'][0])
				))
			{
				//上传图片
				$uparray = $upload->BeginUpload('infoImg48', array('jpg','png','gif'), 1024*50);
				if(0 < $uparray[0]['error'])
				{
					$this->webVar['errMsg'] = $uparray[0]['errorMsg'];
					$this->EditAd($chnId);
					exit;
				}
				else if(false === $uparray[0]['extPass'])
				{
					$upload->Cancel($uparray);
					$this->webVar['errMsg'] = '不允许上传这种图片文件类型!';
					$this->EditBase($chnId);
					exit;
				}
				else if(false === $uparray[0]['sizePass'])
				{
					$upload->Cancel($uparray);
					$this->webVar['errMsg'] = '图片文件过大,不允许上传!';
					$this->EditBase($chnId);
					exit;
				}
				else
				{
					//合法
					$upTargDir = $this->GetSavePath($chnId);
					$filename = GenTimeFileName().'.'.$uparray[0]['ext'];

					$ret = move_uploaded_file($uparray[0]['tmp_name'], $upTargDir.$filename);
					if(false === $ret)
					{
						$this->webVar['errMsg'] = '图片文件保存失败!';
						$this->EditBase($chnId);
						exit;
					}
					chmod($upTargDir.$filename, 0660);

					//删除旧文件
					if(0 < strlen($attr['infoImg48']))
					{
						unlink($upTargDir.$attr['infoImg48']);
					}
					//设置新文件
					$attr['infoImg48'] = $filename;
				}
			}


			$chnInfo['attr'] = json_encode($attr);
			//var_dump($chnInfo['attr']);

			$chnDal->where(array('id'=>$chnInfo['id']))->save($chnInfo);
			$this->EditBase($chnId);
		}
		else
		{
			$this->webVar['errMsg'] = '非法操作！';
			$this->EditBase($chnId);
			exit;
		}

	}


	public function TypeComboxData()
	{
		echo '[{"value":"public","name":"公开"},{"value":"charge","name":"收费"},{"value":"protect","name":"认证"},{"value":"private","name":"会员"}]';
	}

	public function StatusComboxData()
	{
		echo '[{"value":"normal","name":"开启"},{"value":"disable","name":"停用"}]';
	}

	public function TplComboxData()
	{
		//暂不使用娱乐类
		//echo '[{"value":"play","name":"默认模板"},{"value":"playPay","name":"娱乐类"}]';
        echo '[{"value":"play","name":"默认模板"},{"value":"playFlex","name":"弹性模板"}]';
	}

	public function PlayTypeComboxData()
	{
		echo '[{"value":"auto","name":"自动识别"},{"value":"record","name":"录像模式"},{"value":"live","name":"直播模式"}]';
	}

	protected function IsAdminRole($userInfo = null)
	{
		if(null === $userInfo)
		{
			$userInfo = authorize::getUserInfo();
		}
		$userRoleDal = D('userrelrole');
		
		$this->webVar['isAdminRole'] = $this->isOpPermit('F');

		//var_dump($this->webVar['isAdminRole']);

		return $this->webVar['isAdminRole'];
	}

	public function nochatList($page, $rows)
	{
		if(!pagination::isAvailable('NoChat'))
		{
			//新的查询
			$cond=condition::get('NoChat');
			$cond['chnid'] = $cond['chnId'];
			unset($cond['work']);
			unset($cond['chnId']);
			$cond=arrayZip($cond,array(null,0,'不限','0','','全部'));
			
			$db=D('channelnochat');
			$rec=$db->field('c.name chnname, u.account, u.username, chnid, uid')->where($cond)->join('__CHANNEL__ c on chnid=c.id')->join('__USER__ u on uid=u.id')->select();
			//echo $db->getLastSQL();
			//var_dump($rec);
			
			pagination::setData('NoChat', $rec);
		}
		$result=array();
		
		$data=pagination::getData('NoChat',$page,$rows);
		$result["rows"]=$data;
		$result["total"]=$rows;
		if(null==$result)	echo '[]';
		else echo json_encode($result);

	}

	public function noChatMgrAjax($para='')
	{
		$status=$para['status'];
		
		$rows=$para['rows'];
		$cond='0=1';
		foreach ($rows as $key=>$rec){
			$cond .=" or chnid=".$rec['chnid']." and uid=".$rec['uid'];
		}
		$db=D('channelnochat');
		$result=$db->where($cond)->delete();
		$ret=(false===$result)?'{"success":"false"}':'{"success":"true"}';
		//清空数据
		pagination::clear('NoChat');
	}

	public function NoChatMgr()
	{
		$this->baseAssign();
 		$this->assign('mainTitle','禁言管理');
		//$this->assign('userName',$this->userName());

		$webVarTpl=array('work'=>'init','chnId'=>'0');
  		condition::clear('NoChat');
 		pagination::clear('NoChat');
 		$webVar=$this->getRec($webVarTpl,false);

 		if('init'==$webVar['work']){
 			
 			//取下拉频道数据
 			$userInfo=authorize::getUserInfo();
 			$db=D('userrelrole');
 			$isAdmin=$db->isInRole($userInfo['userId'],C('adminGroup'));
 			$db=D(channel);
 			$chnList=($isAdmin)?$db->getPulldownList():$db->getPulldownList($userInfo['userId']);
 			if($isAdmin) {
 				$chnList=array_merge(array(array('id'=>0,'name'=>'全部')),$chnList);
 				//$webVar['chnId']=0;
 			} else if(count($chnList)<1){
 				//没有任何频道的管理权限
 				//$this->display('common:noRight');
 				//return;
 			} else {
 				$webVar['chnId']=$chnList[0]['id'];
 			}
 			//dump($chnList);
 			$chnListJson=(null==$chnList)?'[]':json_encode($chnList);
 			setPara('chnListJson', $chnListJson);
 			condition::save($webVar,'NoChat');	//更新并存储最新的查询条件
			$cond=condition::get('NoChat');
 		} else {
 			condition::update($this->onlineUserCond,ACTION_NAME);
 		}

		$this->assign('chnId', $webVar['chnId']);
		$this->assign('work', $webVar['work']);
		$this->display('NoChatMgr');
	}

	public function Edit($chnId = '')
	{
		$this->baseAssign();
 		$this->assign('mainTitle','频道属性');
		//$this->assign('userName',$this->userName());


		//生成频道下拉列表数据
		$isAdmin = $this->IsAdminRole();
	
		$userInfo=authorize::getUserInfo();
		$chnDal = new ChannelModel();
		$chnList = $chnDal->GetUserChnList($userInfo);

		//默认值
		if('' === $chnId)
		{
			$this->webVar['chnId'] = $chnList[0]['id'];
		}
		else
		{
			$this->webVar['chnId'] = $chnId;
		}

		//下拉频道数据
		$chnListJson = (null == $chnList) ? '[]' : json_encode($chnList);
		setPara('chnListJson', $chnListJson);


		$this->assignB($this->webVar);
		$this->display('Edit');
	}

	public function GetChnComboxData(){
		echo getPara('chnListJson');
	}

	public function RecordList()
	{
		pagination::clear('recordSearch');
		$this->AssignPage('record');
		$this->assignB($this->webVar);
		$this->display();
	}

	public function RecordSearch()
	{
		$this->recordSearchData();
		$this->AssignPage('record');
		$this->assignB($this->webVar);
		$this->display('RecordList');
	}

	//观看累计计数
	public function ViewInc($chnAttr = null, $chnInfo = null, $chnId = 0)
	{
		//当前数为100内总以1累加
		$chnDb = new ChannelModel();
		if(null == $chnInfo['entrytimes']
			|| 100 >= $chnInfo['entrytimes'] )
		{
			$chnDb->where('id='.$chnId)->save(array('entrytimes' =>array('exp','entrytimes + 1')) );
		}
		else
		{
			if(empty($chnAttr['viewIncRand']))
			{
				$chnAttr['viewIncRand'] = 1;
			}
			$num = rand(1, $chnAttr['viewIncRand']);
			$chnDb->where('id='.$chnId)->save(array('entrytimes' =>array('exp','entrytimes + '.$num)) );
		}
	}

	//获取某用户拥有的频道数，或全局频道数
	public function channelNum($userId = 0)
	{
		$dbChannel=D('Channel');
		$chnList=$dbChannel->getListByOwner($this->userId(), 'id');
		$channels=count($chnList,COUNT_NORMAL);
		return $channels;
	}

	//============append 2018-05-11 outao
    /**
	 * 取频道可用礼品列表
     * @param int $chnId 频道ID
     */
	public function giftListJson($chnId=0){
        header("Access-Control-Allow-Origin:*");
        header("Access-Control-Allow-Methods:*");
        header("Access-Control-Allow-Headers:*");
        $dbChannel=D('Channel');
        $dbGoods=D('Goods');
        $userLevel=$this->author->getUserInfo('userlevel');
        $vipLevel=$this->author->getUserInfo('viplevel');
        //var_dump($userLevel,$vipLevel);
        //dump($_SESSION['userinfo']);
        try{
        	if($chnId<1) throw new Exception('频道ID错误：'.$chnId);
            $gift=getExtAttr($dbChannel,array("id"=>$chnId),"gift");
            //dump($gift);
            $cond=array('category'=>'virtual','status'=>'正常');

            //频道有定义限定礼物范围，没定义取全部礼物
            if(count($gift)>0) {
                $goodIds='';
                foreach ($gift as $key=>$val){
                	if(''==$goodIds) $goodIds=$val['id'];
                	else $goodIds .=','.$val['id'];
				}
				$cond['id']=array('in',$goodIds);
            }

            $fields='id,price_c as price,name,detail,picture,userlevel,viplevel';
            $goods=$dbGoods->where($cond)->field($fields)->select();
            //echo $dbGoods->getLastSql();
            foreach ($goods as $key=>$val){
            	$goods[$key]['order']=$this->searchOrder($gift,$val['id']);
            	$goods[$key]['picture']=$dbGoods->getFullImgUrl($val['picture']);
            	if(0!=$val['userlevel'] && $val['userlevel']>$userLevel) $goods[$key]['disableNote']='要求用户等级'.$val['userlevel'].'以上。';
            	elseif(0!=$val['viplevel'] && $val['viplevel']>$vipLevel) $goods[$key]['disableNote']='要求VIP等级'.$val['viplevel'].'以上。';
            	else $goods[$key]['disableNote']='';
			}


			//dump($goods);
            echo json_encode($goods,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
		}catch (Exception $e){
        	echo '[]';	//返回空数组
			logfile($e->getMessage(),LogLevel::WARN);
		}
        return;
        //echo $dbChannel->getLastSql();


	}
	//在$gift按商品ID，查找排列顺序
	private function searchOrder($gift,$goodId){
		foreach ($gift as $key=>$val){
			if($val['id']==$goodId) return intval($val['order']);
		}
		return 0;
	}

	const CHNCATEGORY='chncategory';	//频道分类编码在数据字典中的分类
	public function chnCategoryJson(){
		$dbDict=D('Dictionary');
		$cond=array("category"=>self::CHNCATEGORY,"sorder"=>array("EGT",0));
		$list=$dbDict->where($cond)->field("ditem as id, dname as text")->order("sorder")->select();
		//dump($list);
		//echo $dbDict->getLastSql();
		Oajax::ajaxReturn($list);
	}

    /**
	 * 频道图片直播管理
     * @param int $chnId
     * @param null $bozhu
     */
	public function photoMan(){
		$this->baseAssign();
		$webVarTpl=array('chnName'=>'', 'bozhu'=>'', 'work'=>'init');
        $webVar=getRec($webVarTpl,false);

        $webVar['comboData']='[]';
		if('true'!=$this->isAdmin){
			$webVar['bozhu']=$this->getUserInfo('account');
		}
        //dump($webVar);
		$dbChn=D('Channel');
		$dbUser=D('User');
		if('search'==$webVar['work']){
			if(null!=$webVar['bozhu']){
                $uid=$dbUser->getUserId($webVar['bozhu']);
                //echo $dbUser->getLastSql();
                //var_dump($uid);
                if(0==$uid) {
                	$uid=-1;
                	$webVar['msg']='找不到此账号，账号需要完整输入！';
                }
			}else {
				$uid=0;
			}
            $combItem=$dbChn->getPulldownList($uid,'','',$webVar['chnName']);
			$webVar['comboData']=json_encode($combItem);
			$webVar['selected']=$combItem[0]['id'];	//选择最先的选项
		}

        $webVar['isAdmin']=$this->isAdmin;
        $this->assign($webVar);
        $this->display();
	}

	public function photoListAjax(){
		$chnId=$_POST['id'];	//频道ID
		$chnName=$_POST['name'];	//频道名称

		$webVar['chnId']=$chnId;
		$webVar['chnName']=$chnName;
        $webVar['photos']=$this->getPhotoList($chnId);
        //dump($webVar['photos']);
		$webVar['path']=C('roomImgUpload').$this->GetChnFload($chnId);
		$this->assign($webVar);
		$this->display();
	}

    /**
     * @param $chnId
     * @return array 图片数组 包含2列：imgsrc-图片URL, imgName-图片文件名
     */
	protected function getPhotoList($chnId){
		$dir=C('roomImgUpload').$this->subpath($chnId).'photoM/';
		$baseUrl=C('roomImgView').$this->subpath($chnId).'photoM/';
		$photos=array();
		/*
		if($handle=opendir($dir)){
			while(false !==($file = readdir($handle))){
				$p=strripos($file,'.JPG')+4;
				//dump($p);
				if($p!=strlen($file)) continue;
                array_push($photos,array('imgsrc'=>$baseUrl.$file,'imgName'=>$file));
            }
            closedir($handle);
		}
		*/
		$cond=array('chnid'=>$chnId);
		$photoList=D('photo')->where($cond)->order('uploadtime desc')->select();
		foreach ($photoList as $row){
			if(is_file($dir.$row['uuname'])){
            	array_push($photos,array('imgsrc'=>$baseUrl.$row['uuname'],'imgName'=>$row['uuname']));
			}

		}
		return $photos;
	}

	public function showPhoto($chnId){
		//dump($_POST);
		$work=$_POST['work'];
		if("loadPage"==$work) {
            $photos = $this->getPhotoList($chnId);
            $webVar=array("photos"=>$photos);
            $this->assign($webVar);
            $this->display("showPhotoPage");
        }
		else{
            $photos=$this->getPhotoList($chnId);
            $webVar['photos']=$photos;
            $webVar['chnId']=$chnId;
            $this->assign($webVar);
            $this->display("showPhoto_m");
		}
	}

	//显示指定图片的原图及相关信息
	public function showPhotoDetail($chnId,$photoName){
		$webVar=array("chnId"=>$chnId,"photoName"=>$photoName);
        $webVar['dataRec']=json_encode2($webVar);
		//读出图片记录（若存在）
        $prefix=C('DB_PREFIX');
		$photoRec=D('photo photo')->field('uploadtime,desc,praise,value,username')->join("{$prefix}user user on photo.uploader=user.id")->where(array('uuname'=>$photoName))->find();
//echo M()->getLastSql();
//dump($photoRec);
		if(null==$photoRec){
			//数据库无此记录，则用空数据显示
			$photoRec=array('uploadtime'=>'', 'desc'=>'', 'praise'=>0, 'value'=>0, 'username'=>'未知');
		}
		$webVar=array_merge($webVar,$photoRec);

        $baseUrl=C('roomImgView').$this->subpath($chnId).'photoOrg/';
        $webVar['photoUrl']=$baseUrl.$photoName;

		$this->assign($webVar);
		$this->display("showPhotoDetail_m");
	}

    /**
     * 增加点赞数
	 * 输入POST参数：
	 * 	- chnId	频道ID
	 * 	- photoName	图片UUName
	 * 返回Json对象success, praise:最新的点赞数
	 */
	public function plusPraiseJson(){
		//var_dump($_REQUEST);
		$chnid=$_POST['chnId'];
		$uuname=$_POST['photoName'];
		if(null==$chnid || null==$uuname) Oajax::errorReturn('缺少必要的参数。');
		$dbPhoto=D('photo');
		$cond=array('uuname'=>$uuname,'chnid'=>$chnid);
		$rt=$dbPhoto->where($cond)->setInc('praise',1);
		$zhan=0;	//准备返回的点赞值
		if(1==$rt){
			//正常会修改且只一条记录，读取最新的点赞值
			$zhan=$dbPhoto->where($cond)->getField('praise');
		}elseif (0==$rt){
			//无此记录
			//TODO:
		}else{
			//数据库操作出错
			//TODO:
		}
		Oajax::successReturn(array('praise'=>$zhan));
	}
    /**
     *
     * 图片直播中图片文件上传的后台处理程序
     */
    public function endpoint() {
        set_time_limit(3600);	//设置最长运行时间，秒
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
logfile(print_r($_REQUEST,true),LogLevel::DEBUG);
logfile("method:".$method,LogLevel::DEBUG);

        //Insert by outao 2017-04-10
        $uploader->useUuid=false;	//不建立UUID子目录

        $basePath=$_REQUEST['path'];
		$vodname =$_REQUEST['qqfilename'];
		//修改为唯一文件名
        $vodname=OreplaceBaseName($_REQUEST['qqfilename'],Ouuid());
        $vodpath=$_REQUEST['path'].'photoOrg/';
logfile('Upload path:'.$vodpath.' name:'.$vodname,LogLevel::INFO);

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
//logfile('call：handleUpload return:'.print_r($result,true),LogLevel::DEBUG);
                // To return a name used for uploaded file you can use the following line.
                $result ["uploadName"] = $uploader->getUploadName ();
//logfile('file:'.$result ["uploadName"],LogLevel::DEBUG);
				//大图片
				$filePath=$basePath.'photoL/'.$vodname;
                //$image = new imgcompress($vodpath.$vodname,1);
                //$image->compressImg($filePath,1920);
logfile("L file:".$filePath,LogLevel::DEBUG);
                //$image = (new imgcompress($vodpath.$vodname,1))->compressImg($basePath.'/photoL/'.$vodname,1920);
				//中图片
                $filePath=$basePath.'photoM/'.$vodname;
logfile("M file:".$filePath,LogLevel::DEBUG);
                //$image ->compressImg($filePath,480);
                //unset($image);
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
     * 上传图片成功
     */
    public function postUploadJson(){
        logfile(json_encode($_REQUEST),LogLevel::DEBUG);
        $basePath=$_POST['path'];
        $sourceFileName=$_POST['name'];	//上传文件原来的名字
        $fileName=$_POST['uploadName'];	//存储到服务器上的名字
		$chnId=$_POST['chnId'];

        $image = new imgcompress($basePath.'photoOrg/'.$fileName,1);
        //大图片
        $filePath=$basePath.'photoL/'.$fileName;
        $image->compressImg($filePath,1920);
        logfile("L file:".$filePath,LogLevel::DEBUG);

        //中图片
        $filePath=$basePath.'photoM/'.$fileName;
        logfile("M file:".$filePath,LogLevel::DEBUG);
        $image ->compressImg($filePath,480);
        unset($image);

        //填写数据库记录
		$record=array('chnid'=>$chnId,
			'uploader'=>$this->userId(),
			'uuname'=>$fileName,
			'sourcename'=>$sourceFileName
			);
		D('photo')->add($record);

		$arr=array();
		$arr['imgsrc']=C('roomImgView').$this->subpath($chnId).'photoM/'.$fileName;	//新图片的URL
		$arr['imgName']=$fileName;
        Oajax::successReturn($arr);
    }

    /**
     * @param $chnId
     * @return string 根据频道ID生成存储子目录的路径
     */
    protected function subpath($chnId){
    	$path=sprintf("%03d/%02d/",$chnId/100,$chnId%100);
    	return $path;
	}


	public function deletePhoto(){
    	$chnId=$_POST['chnId'];
        $chnName=$_POST['chnName'];
        $files=$_POST['files'];
        $basePath=C('roomImgUpload').$this->subpath($chnId);
        $nb=0;
        foreach ($files as $file){
        	$rt=unlink($basePath.'photoM/'.$file); if($rt) $nb++;
            $rt=unlink($basePath.'photoL/'.$file);if($rt) $nb++;
            $rt=unlink($basePath.'photoOrg/'.$file);if($rt) $nb++;
		}
    	Oajax::successReturn(array('deleteFiles'=>$nb));
	}
}
?>
