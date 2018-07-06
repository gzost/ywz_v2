<?php
/**
 * 管理用户认证授权，权限判断等业务
 */
class FileUpload {

	public function __construct()
	{
	}

	/**
	 * 
	 * 检查上传文件的合法性
	 * 
	 * @param array varName 变量名称
	 * @param array extAllow 允许上传的文件后缀数组 exp: array('jpg','png')
	 * @param int maxSize 允许上传的文件最大大小 单位:BYTE exp:1024*10 表示限制文件不能大于10K
	 * 
	 * @return 复合类型
	 * 	- true	表示上传文件合格
	 * 	- false	未登录
	 */
	public function BeginUpload2($varName, $extAllow = null, $maxSize = 0)
	{
		$retArray = $this->BeginUpload($varName, $extAllow, $maxSize);
		try
		{
			foreach($retArray as $i => $v)
			{
				if(0 < $v['error'])
				{
					throw new Exception($v['errorMsg']);
				}
				else if(false === $v['extPass'])
				{
					throw new Exception('不允许上传这种文件类型!');
				}
				else if(false === $v['sizePass'])
				{
					throw new Exception('文件过大,不允许上传!');
				}
			}
		}
		catch(Exception $e)
		{
			$this->Cancel();
			throw new Exception($e->getMessage());
		}
		return $retArray;
	}

	/**
	 * 
	 * 检查上传文件的合法性
	 * 
	 * @param array varName 变量名称
	 * @param array extAllow 允许上传的文件后缀数组 exp: array('jpg','png')
	 * @param int maxSize 允许上传的文件最大大小 单位:BYTE exp:1024*10 表示限制文件不能大于10K
	 * 
	 * @return 复合类型
	 * 	- true	表示上传文件合格
	 * 	- false	未登录
	 */
	public function BeginUpload($varName, $extAllow = null, $maxSize = 0)
	{
		$isExtPass = false;
		$isSizePass = false;
		$retArray = array();

		if(!is_array($_FILES[$varName]['name']))
		{
			$_FILES[$varName]['name'] = array($_FILES[$varName]['name']);
			$_FILES[$varName]['type'] = array($_FILES[$varName]['type']);
			$_FILES[$varName]['tmp_name'] = array($_FILES[$varName]['tmp_name']);
			$_FILES[$varName]['error'] = array($_FILES[$varName]['error']);
			$_FILES[$varName]['size'] = array($_FILES[$varName]['size']);
		}

		foreach($_FILES[$varName]['name'] as $i => $v)
		{
			$retItem = array();
			$retItem['name'] = $v;
			$retItem['tmp_name'] = $_FILES[$varName]['tmp_name'][$i];
			$retItem['type'] = $_FILES[$varName]['type'][$i];
			$retItem['size'] = $_FILES[$varName]['size'][$i];
			$retItem['error'] = $_FILES[$varName]['error'][$i];
			$retItem['extPass'] = false;
			$retItem['sizePass'] = false;

			switch($_FILES[$varName]['error'][$i])
			{
				case 0:
					$retItem['errorMsg'] = '上传成功。';
					break;
				case 1:
					$retItem['errorMsg'] = '文件大小超过允许上传值upload_max_filesize。';
					break;
				case 2:
					$retItem['errorMsg'] = '文件大小超过HTML表单max_file_size设置值。';
					break;
				case 6:
					$retItem['errorMsg'] = '没有找到临时文件夹。';
					break;
				case 7:
					$retItem['errorMsg'] = '文件写入失败。';
					break;
				case 8:
					$retItem['errorMsg'] = '文件上传扩展没有打开。';
					break;
				case 3:
					$retItem['errorMsg'] = '文件只有部分被上传。';
					break;
			}

			$fileExt = substr(strrchr($v, '.'), 1);
			$retItem['ext'] = $fileExt;

			if(null === $extAllow)
			{
				$retItem['extPass'] = true;
			}
			else
			{
				foreach($extAllow as $i => $v)
				{
					if($v === $fileExt)
					{
						$retItem['extPass'] = true;
					}
				}
			}

			if(0 === $maxSize)
			{
				$retItem['sizePass'] = true;
			}
			else if($maxSize >= $retItem['size'])
			{
				$retItem['sizePass'] = true;
			}

			$retArray[] = $retItem;
		}


		return $retArray;
	}


	/**
	 * 
	 * 检查上传文件的合法性
	 * 
	 * @param array varName 变量名称
	 * 
	 * @return 复合类型
	 * 	- true	表示上传文件合格
	 * 	- false	未登录
	 */
	public function Cancel($array)
	{
		foreach($array as $i => $v)
		{
			unlink($v['tmp_name']);
		}
		//unlink($_FILES[$varName]['tmp_name']);
	}

	public function EndUpload($saveArray)
	{
		foreach($saveArray as $i => $v)
		{
			move_uploaded_file($v['tmp_name'], $v['saveName']);
		}
	}
}
?>