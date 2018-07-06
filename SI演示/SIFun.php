<?php

function HttpPost($url = '', $data = array(), $context = null)
{
	if(null == $context)
	{
		$context = array('http' => array('method' => 'POST',
										'header' => "User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US) \r\n Accept: */*",
										'content' => $data
										) );
	}
	$strContext = stream_context_create($context);
	$feedBack = file_get_contents($url, FALSE, $strContext);
	return $feedBack;
}

class SiDemo{

	protected $_ywzdomain = 'www.av365.cn';
	protected $_chnId = 1055;
	protected $_account = 'zjmzjm3';
	protected $_viewAcc = '可变昵称';
	protected $_secKey = 'siseckey';
	protected $_tm = '';
	protected $_uri = '';
	protected $_stream = 's822f893b0f';
	protected $_vodid = 3214;

    public function index(){
		$this->display();
    }

	protected function makeSec()
	{
		$md5 = md5($this->_secKey.$this->_uri.$this->_account.$this->_tm);
		return $md5;
	}

	public function live()
	{
		$this->_tm = sprintf("%x",time()+600);
		$this->_uri = "/home.php/SI/play/chnId/".$this->_chnId;
		$url = 'http://'.$this->_ywzdomain.$this->_uri."?account=".$this->_account."&sec=".$this->makeSec()."&tm=".$this->_tm."&nickname=".urlencode($this->_viewAcc);

		//echo $url;
		header("location:".$url);
	}

	public function vod()
	{
		$this->_tm = sprintf("%x",time()+600);
		$this->_uri = "/home.php/SI/vod/vodid/".$this->_vodid;
		$url = 'http://'.$this->_ywzdomain.$this->_uri."?account=".$this->_account."&sec=".$this->makeSec()."&tm=".$this->_tm."&nickname=".urlencode($this->_viewAcc);

		//echo $url;
		header("location:".$url);
	}

	public function mylive()
	{
		$this->_tm = sprintf("%x",time()+600);
		$this->_uri = "/home.php/SI/getPlayUri/stream/".$this->_stream;

		$url = 'http://'.$this->_ywzdomain.$this->_uri."?account=".$this->_account."&sec=".$this->makeSec()."&tm=".$this->_tm;

		//echo $url;
		$cont = '';
		$cont = HttpPost($url);
		//var_dump($cont);
		$json = json_decode($cont, true);
		//var_dump($json);

		$mrl = '';

		if('true' == $json['success'])
		{
			$mrl = $json['uri'];
		}

		$html = <<<EOT
		<video id="h5Player" controls="controls" poster="http://www.av365.cn/player/default/images/videobg.jpg" x5-vide playsinline="playsinline" webkit-playsinline="true" x-webkit-airplay="false" style="position:relative;width:100%;margin:0;max-height:100%" src="$mrl" type="video/mp4">
		</video>
EOT;

		echo $html;

	}

	public function myvod()
	{
		$this->_tm = sprintf("%x",time()+600);
		$this->_uri = "/home.php/SI/getVodUri/vodid/".$this->_vodid;

		$url = 'http://'.$this->_ywzdomain.$this->_uri."?account=".$this->_account."&sec=".$this->makeSec()."&tm=".$this->_tm;

		//echo $url;
		$cont = '';
		$cont = HttpPost($url);
		//var_dump($cont);
		$json = json_decode($cont, true);
		//var_dump($json);

		$mrl = '';
		if('true' == $json['success'])
		{
			$mrl = $json['uri'];
		}

		$html = <<<EOT
		<video id="h5Player" controls="controls" poster="http://www.av365.cn/player/default/images/videobg.jpg" x5-vide playsinline="playsinline" webkit-playsinline="true" x-webkit-airplay="false" style="position:relative;width:100%;margin:0;max-height:100%" src="$mrl" type="video/mp4">
		</video>
EOT;
		echo $html;

	}
}

?>