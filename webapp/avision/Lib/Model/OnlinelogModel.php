<?php
class OnlinelogModel extends Model {

	public function GetIpLocat()
	{
		ob_start();
		$w = array();
		$w['location'] = '';
		$w['clientip'] = array('exp', 'is not null');
		//获取未知记录的最小ID
		$minId = $this->where($w)->min('id');
		echo $this->getLastSQL();

		$w['id'] = array('egt', $minId);
		$index = 0;
		$data = $this->field('clientip')->group('clientip')->where($w)->select();
		echo '   total:'.count($data);
		ob_flush();
		flush();
		ob_end_flush();
		foreach($data as $i => $row)
		{
			//获取未知IP列表
			$arr = $this->GetIpData($row['clientip']);
			//var_dump($arr);
			$locat = '';
			if(!empty($arr['country']))
			{
				$locat .= $arr['country'];
			}
			if(!empty($arr['province']))
			{
				$locat .= ','.$arr['province'];
			}
			/*
			if(!empty($arr['city']))
			{
				$locat .= ','.$arr['city'];
			}
			*/
			//echo $locat;

			//更新记录

			$w = array();
			$w['id'] = array('egt', $minId);
			$w['clientip'] = $row['clientip'];
			$s = array();
			$s['location'] = $locat;
			//$this->where($w)->save($s);

			$index++;

/*
			echo '<hr>';
			echo $i.':'.$row['clientip'].":".$locat;
			ob_flush();
			flush();

			if(50 < $index)
			{
				$index = 0;
				//sleep(1);
				//usleep(1000000);
				//usleep(10000);
			}
*/
		}
	}

	protected function GetIpData($ip)
	{
		$url = "http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=js&ip=".$ip."&qq-pf-to=pcqq.c2c";

		//var remote_ip_info = {"ret":1,"start":-1,"end":-1,"country":"\u4e2d\u56fd","province":"\u5e7f\u4e1c","city":"\u5e7f\u5dde","district":"","isp":"","type":"","desc":""};

		$str = file_get_contents($url);

		$match = '';
		preg_match("/\{.{20,}\}/",$str,$match);
		if(is_array($match))
		{
			$match = $match[0];
		}

		$arr = json_decode($match, true);

		return $arr;
	}
}
?>