<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/4/4
 * Time: 15:50
 *
 * 代理商数据表操作模型
 */
require_once LIB_PATH.'Model/UserModel.php';
class AgentModel extends Model{

    /**
     * 取用户表字段名显示数组
     * @param int $agentid
     * @return array    '字段名'=>'显示字串'
     */
    public function getUserFieldName($agentid=0){
        $fieldName=UserModel::$fieldName;
        //TODO: 读agent的显示配置，覆盖默认显示字串
        $defName=getExtAttr($this,array("id"=>$agentid),"userfields",'attr');
        if(!empty($defName)){
            foreach ($defName as $key=>$val){
                $fieldName[$key]=$val;  //替换自定义的字段名
            }
        }
        return $fieldName;
    }

    /**
     * 取可导入字段显示串索引表
     * @param $agentid
     * @return array|null "显示字串"=>"字段名"
     */
    public function getUserImportableFieldsName($agentid){
        $importableFields=UserModel::$importTableFields;
        $fieldName=$this->getUserFieldName($agentid);   //取自定义后的字段显示串
        //用自定义显示串，替换导入的字段显示名
        foreach ($importableFields as $key=>$val){
            if(!empty($fieldName[$key])) $importableFields[$key]=$fieldName[$key];
        }
        return array_flip($importableFields);
    }
    /**
     * 取代理名称列表
     * @return mixed
     */
    public function getNameList(){
        $recs=$this->field('id,name')->select();
        return $recs;
    }
}