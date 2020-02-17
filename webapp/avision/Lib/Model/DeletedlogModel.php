<?php
require_once APP_PATH.'../public/Ou.Function.php';
class DeletedlogModel extends Model {

    /**
     * 写入删除日志
     * @param array $data   记录数组
     * @param $tablename    表名
     *
     * @throws Exception 'Deleted log 写入失败
     */
    public function saveRec($data,$tablename){
        if(empty($data) || empty($tablename)) return ;

        $record=array('tablename'=>$tablename, 'deletetime'=>date("Y-m-d H:i:s"));
        $record['record']=json_encode2($data);
        if(!empty($data['id'])) $record['recordid']=$data['id'];
        elseif(!empty($data['Id'])) $record['recordid']=$data['Id'];
        else $record['recordid']=0;
        $rt=$this->add($record);
        if(false==$rt){
            logfile("DeletedlogModel.saveRec写入失败，tablename=".$tablename);
            throw new Exception('Deleted log 写入失败');
        }
    }
}
?>