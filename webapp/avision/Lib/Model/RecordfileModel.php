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
        if(null == $item) return '';

		//以http开头的，不需要转换
		if(preg_match("/^http/", $item['path'])) {
			return $item['path'];
		}

		//获取mrl转换规则
		$cond=array('category'=>'vod', 'ditem'=>'url' );
		$dicDal = D('dictionary');
		$record=$dicDal->where($cond)->field('ditem,dname,dvalue,attr')->find();

		if(false==$record) return '';
		$attr=json_decode($record['attr'],true);

		$str=str_replace('%%filepath%%', $item['path'], $attr['mrl']);  //VOD播放地址，未处理防盗链部分
        $mode=$attr['mode'];
        if(null==$mode) $mode='';
        $functionName='secret'.$mode;
//echo $functionName," _str=",$str;
        if(true==method_exists ($this,$functionName)){
            //对mrl进行防盗链等进一步的处理
            $ss = C('vodfile_base_path').$item['path'];
            $str=$this->$functionName($ss, $str,$attr['cdnkey']);

        }
//echo "<br>ret=".$str;
        return $str;

var_dump( method_exists ($this,"secreta") );
		if(false!==strpos($attr['mrl'], '%%secret%%')){	//需要生成时间戳防盗链
			$ss = C('vodfile_base_path').$item['path'];
			$str=$this->secret($ss, $str,$attr['cdnkey']);
		}

		return $str;
	}

    /**
     * @param $stream   点播文件的路径
     * @param $url      点播资源URL模板，已经嵌入点播文件路径
     * @param $cdnkey   CDN约定的通讯默默
     * @return string   向CDN申请点播流的URL
     */
	private function secretAC($stream,$url,$cdnkey){
        $keeptime=platform::EFTIME;   //平台定义的有效时长
        $defaultKeeptime=1800;  //CDN默认的有效时长（秒）
	    $timestamp=sprintf("%08x",time()+$keeptime-$defaultKeeptime); //
	    $rand=mt_rand(1000,9999);
	    $uid=0;
	    $sstring=sprintf("%s%s%s",$cdnkey,$stream,$timestamp);
	    $md5hash=md5($sstring);
	    $secret=$md5hash;
        $str=str_replace('%%secret%%',$secret,$url);
        $str=str_replace('%%keeptime%%',$timestamp,$str);
//dump($sstring); dump($md5hash);
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
     * @param string $orgPath 输入文件相对路径及旧文件名，输出新的相对路径及名称
	 * @return int 录像文件大小MB，0-失败
	 */
	public function updateVideoSize($id, &$orgPath=''){
	    try{
	        if($id<=0) throw new Exception('illegal id.');
	        $record=array();    //准备要更新的记录数据
            $path='';    //原文件名及相对路径
            $oldFile=$this->getVideoUrl($id,$path);  //同时获取原文件的相对路径机文件名，
            $filePath=getcwd().$oldFile;

            if(!is_file($filePath)) throw new Exception('Cannot find file:'.$filePath);
//echo $filePath;
//var_dump($rename);
            $fileSize=ceil(filesize($filePath)/1024/1024);	//转成MB
            $record['size']=$fileSize;
            if(''!=$orgPath){
                $newName=uniqid($id.'_').'.';
                $pathParts=pathinfo($path);   //相对路径
                $basePath=(''==C(vodfile_base_path))?'/vodfile':C(vodfile_base_path);
                $newFilePath=getcwd().$basePath.$pathParts['dirname'].'/'.$newName.$pathParts['extension'];
logfile("updateVideoSize: newFilePath=$newFilePath", LogLevel::DEBUG);
                $rt=rename($filePath,$newFilePath);
                if(true==$rt){
                    $orgPath=$pathParts['dirname'].'/'.$newName.$pathParts['extension'];
                    $record['path']=$pathParts['dirname'].'/'.$newName.$pathParts['extension'];
logfile("rename=".$rt,LogLevel::DEBUG);
                    //修改视频封面图片名称
                    $patter = "/.mp4$/";
                    $rep = '.jpg';
                    $oldCoverPath = preg_replace($patter, $rep, $filePath);
                    $newCoverPath = preg_replace($patter, $rep, $newFilePath);
                    $rt=rename($oldCoverPath,$newCoverPath);
logfile("rename: $oldCoverPath,$newCoverPath return:$rt", LogLevel::DEBUG);
                }
            }
            $this->where('id='.$id)->save($record);
            return $fileSize;
        }catch (Exception $e){
	        logfile("updateVideoSize:".$e->getMessage(),LogLevel::WARN);
	        return 0;
        }

	}
	
	/**
	 * 
	 * 取指定记录录像文件的URL路径，出错抛出错误
	 * @param int $id
     * @param string $path  数据库记录的相对路径及文件名
     * @return string
     * @throws Exception
	 */
	public function getVideoUrl($id,&$path=''){
		$path=$this->where('id='.$id)->getField('path');
		if(false==$path || strlen($path)<3) throw new Exception('找不到录像路径。');
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

    /**
     * 根据录像封面图片的相对路径取绝对存储路径
     * @param string $RelativePath  录像封面图片的相对路径
     * @return string
     */
	static public function getImgPhysicalPath($RelativePath){
	    $baseUrlPath=(''==C(vodfile_base_path))?'/vodfile':C(vodfile_base_path);
        return $_SERVER["DOCUMENT_ROOT"].$baseUrlPath.$RelativePath;
    }

    /**
     * 将指定的文件改名以匹配指定的录像记录视频文件名
     * @param $id   录像记录id
     * @param $coverFile    封面文件及其相对路径
     * @return string   新的文件名及相对路径
     */
    public function setCover($id,$coverFile){
        $path=$this->where('id='.$id)->getField('path');
        $basePath=(''==C(vodfile_base_path))?'/vodfile':C(vodfile_base_path);
        $orgCoverPath=getcwd().$basePath.$coverFile;
        $newCoverPath=getcwd().$basePath.$path;
        $patter = "/.mp4$/";
        $rep = '.jpg';
        $orgCoverPath = preg_replace($patter, $rep, $orgCoverPath);
        $newCoverPath = preg_replace($patter, $rep, $newCoverPath);
        $rt=rename($orgCoverPath,$newCoverPath);
        return preg_replace($patter, $rep, $path);
    }

    /**
     * 增加观看计数。无论增加是否成功，不返回信息
     * @param int $id 记录ID
     * @param int $inc  增长值
     */
    public function incAudience($id, $inc=1){
        $id=intval($id);
        if($id<1) return;

        $monthDetail=$this->where("id=$id")->getField("monthdetail");
//var_dump($monthDetail);
        $monthArr=json_decode($monthDetail,true);
        if(!is_array($monthArr)) $monthArr=array();
//var_dump($monthArr);
        $this->incMonthDetail($monthArr,$inc);
//var_dump($monthArr);
        $monthDetail=json_encode($monthArr);
        $data=array("monthdetail"=>$monthDetail);
        $data['viewers']=array('exp','viewers+'.$inc);
        $this->where("id=$id")->save($data);
//echo $this->getLastSql();
    }

    private function incMonthDetail(&$detail,$inc){
        $now=time();    //取当前时间
        $today=date("Y-m-d",$now);
        $day=date("j",$now);    //1~31的日期

        //处理当前日期
        if(!empty($detail[$day]) && $detail[$day]['d']==$today){
            $detail[$day]['c'] +=$inc;
        }else{
            //今天第一次更新
            $detail[$day]['d']=$today;
            $detail[$day]['c']=$inc;

        }
    }
}
?>