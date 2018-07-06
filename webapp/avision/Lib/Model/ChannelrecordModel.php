<?php
/**
 * 频道操作模型
 */
require_once APP_PATH.'../public/Ou.Function.php';
require_once(LIB_PATH.'Model/UserrelroleModel.php');
require_once(APP_PATH.'/Common/functions.php');
require_once APP_PATH.'../public/CommonFun.php';


class ChannelrecordModel extends Model {

	public function AddRecord($streamId,$endTime,$recordFile)
	{
		$endTime = substr($endTime, 0, 10);

		$new['stream'] = $streamId;
		$new['endtime'] = $endTime;
		$new['endtimestr'] = date('Y-m-d H:i:s', $endTime);
		$new['recordfile'] = $recordFile;
		return $this->add($new);
	}
}
?>
