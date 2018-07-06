<?php
require_once(APP_PATH.'/Common/platform.class.php');

class RecordfileModel extends Model {
	static public function getRecMrl($recpath)
	{
		if(false == strpos($recpath, 'http'))
		{
			return $recpath;
		}
		else
		{
			return "http://".$_SERVER['HTTP_HOST'].$recpath;
		}
	}

	static public function getImgMrl($imgpath){
		//$path = C('vodfile_save_path');
		$path=(''==C(vodfile_base_path))?'/vodfile':C(vodfile_base_path);

		$patter = "/.mp4$/";
		$rep = '.jpg';
		$imgpath = preg_replace($patter, $rep, $imgpath);

		//录像文件永久存放目录
		$fullPath=$_SERVER["DOCUMENT_ROOT"].$path.$imgpath;
//echo $fullPath,'<br>';
		if(!is_file($fullPath))
		{
			return '/player/default/images/start.jpg';
		}
		else
		{
			return $path.$imgpath;
		}
	}
	
	//根据流ID取流计费属性
	public function getChargeAttr($id){
		$result= $this->where('id='.$id)->field('owner,charge')->find();

		if(null!=$result){
			$rec=json_decode($result['charge'],true);
			$rec['owner']=$result['owner'];
			return $rec;
		}else {
			return null;
		}
	}

	/**
	 * 是否从属关系
	 */
	public function isOwner($userId=0, $recId=0)
	{
		$w = array();
		$w['owner'] = $userId;
		$w['id'] = $recId;
		$c = $this->where($w)->count();
		if(1 == $c)
		{
			return true;
		}
		return false;
	}
	
	/**
	 * 
	 * 取指定用户拥有的录像文件数
	 * @param int $userId
	 * 
	 * @return int	录像文件数
	 */
	public function countByUser($userId){
		$cond=array('owner'=>$userId);
		return $this->where($cond)->count();
	}
	
	/**
	 * 
	 * 根据用户ID返回VOD文件的子目录路径
	 * @param int $owner
	 */
	public function getVodSubdir($owner){
		$dir=sprintf("/%09d",$owner);
		$dir=substr_replace($dir, '/', 4,0);
		$dir=substr_replace($dir, '/', -3,0);
		return $dir;
	}
	/**
	 * 
	 * 根据属主ID获取或建立VOD文件路径
	 * @param int $owner	播主用户ID
	 * 
	 * @return VOD文件基于vodfile_base_path之后的子路径，null路径无法建立
	 * 
	 */
	public function createSubDir($owner){
		$subdir=$this->getVodSubdir($owner);
		$basePath=(''==C(vodfile_base_path))?'/vodfile':C(vodfile_base_path);
		$fulldir=getcwd().$basePath.$subdir;
	//echo $fulldir,'<br>';
		if(!is_dir($fulldir)){
			$rt=mkdir($fulldir,0777,true);	//若不存在则建立
			if(!$rt) return null;	//平台没设置录像路径，因此无法记录录像文件
		}
		return $subdir;
	}
	/**
	 * 
	 * 获取点播地址
	 * @param int $recid	记录ID
	 * 
	 * @return 失败返回''  成功返回访问地址
	 */
	public function getVodMrl($recid){
		//获取vod记录
		$item = $this->where(array('id'=>$recid))->find();

		//以http开头的，不需要转换
		if(preg_match("/^http/", $item['path']))
		{
			return $item['path'];
		}
		if(null == $item) return '';

		//获取mrl转换规则
		$cond=array('category'=>'vod', 'ditem'=>'url' );
		$dicDal = D('dictionary');
		$record=$dicDal->where($cond)->field('ditem,dname,dvalue,attr')->find();

		if(false==$record) return '';
		$attr=json_decode($record['attr'],true);

		$str=str_replace('%%filepath%%', $item['path'], $attr['mrl']);

		if(false!==strpos($attr['mrl'], '%%secret%%')){	//需要生成时间戳防盗链
			$ss = C('vodfile_base_path').$item['path'];
			$str=$this->secret($ss, $str,$attr['cdnkey']);
		}

		return $str;
	}

	/**
	 * 
	 * 生成防盗链URL
	 * @param string $stream
	 * @param string $url
	 * 
	 * @return string 替换了防盗链内容的URL
	 */
	public function secret($stream,$url,$cdnkey){
		$timeStr=sprintf("%08x",time());
		//$timeStr=sprintf("%d",time());

		$keeptime=sprintf("%d",platform::EFTIME);
		$secret=md5($cdnkey.$stream.$timeStr.$keeptime);
//echo 'MD5:',$cdnkey.$stream.$timeStr.$keeptime,'<br>';
		$str=str_replace('%%secret%%',$secret,$url);
		$str=str_replace('%%tm%%',$timeStr,$str);
		$str=str_replace('%%keeptime%%',$keeptime,$str);
		return $str;
	}

	/**
	 * 更新指定记录录像文件的大小信息
	 * @param int $id 记录ID
	 * @return 录像文件大小MB
	 */
	public function updateVideoSize($id){
		$filePath=getcwd().$this->getVideoUrl($id);
		echo $filePath;
		$fileSize=ceil(filesize($filePath)/1024/1024);	//转成MB
		$this->where('id='.$id)->save(array('size'=>$fileSize));
		return $fileSize;
	}
	
	/**
	 * 
	 * 取指定记录录像文件的URL路径
	 * @param unknown_type $id
	 */
	public function getVideoUrl($id){
		$path=$this->where('id='.$id)->getField('path');
		$basePath=(''==C(vodfile_base_path))?'/vodfile':C(vodfile_base_path);
		return $basePath.$path;
	}
	
	/**
	 * 
	 * 统计各播主录像文件占用的空间，以消费记录格式返回
	 * 
	 * @return array	每行记录包括：
	 * 		objtype:	消费类型 固定：storage
	 * 		objid:		消费对象ID：ownerid
	 * 		users:		本时段总在线消费人次:1
	 * 		newusers:	本时段内加入消费的人次:1
	 * 		qty:		占用磁盘空间统计MB
	 * 出错返回：false
	 */
	public function consumpStat(){
		$rec=$this->field('"storage" objtype,owner objid,1 users,1 newusers, sum(size) qty')->group("owner")->select();
//echo $this->getLastSql();
		return $rec;
	}
}
?>