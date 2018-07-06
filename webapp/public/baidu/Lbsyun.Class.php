<?php
/**
 * @file
 * @brief 百度开放平台接口配置
 * @author Rocky
 * @date 2017-05-15
 * 
 */

require_once APP_PATH.'../public/CommonFun.php';

class Lbsyun
{
	/**
	 * 根据IP获取位置名称
	 * 返回格式
array(3) {
  ["address"]=&gt;
  string(34) "CN|广东|广州|None|CHINANET|0|0"
  ["content"]=&gt;
  array(3) {
    ["address_detail"]=&gt;
    array(6) {
      ["province"]=&gt;
      string(9) "广东省"
      ["city"]=&gt;
      string(9) "广州市"
      ["district"]=&gt;
      string(0) ""
      ["street"]=&gt;
      string(0) ""
      ["street_number"]=&gt;
      string(0) ""
      ["city_code"]=&gt;
      int(257)
    }
    ["address"]=&gt;
    string(18) "广东省广州市"
    ["point"]=&gt;
    array(2) {
      ["y"]=&gt;
      string(10) "2629614.08"
      ["x"]=&gt;
      string(11) "12613487.11"
    }
  }
  ["status"]=&gt;
  int(0)
}
	 */
	public function Ip2Address($ip)
	{
		$p = array();
		$p['ip'] = $ip;
		$p['ak'] = LbsyunConf::_LbsKey;
		$ret = GetUrlContent(LbsyunConf::_LbsUrl, $p);
		return $ret;
	}

}


?>
