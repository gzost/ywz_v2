<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/4/2
 * Time: 19:01
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once LIB_PATH.'Model/AgentModel.php';
require_once APP_PATH.'../public/FileUpload.Class.php';
require_once LIB_PATH."Action/ProgressAction.class.php";
/** Include PHPExcel */
require_once C('PHPExeclPath').'PHPExcel.php';
class MG_UserAction extends AdminBaseAction{

    /**
     * 用户CURD统一入口，避免过多public function权限配置困难
     * {"operation":[{"text":"管理本机构用户","val":"R"},{"text":"管理所有用户","val":"A"},{"text":"新增","val":"C"},{"text":"修改","val":"U"},{"text":"删除","val":"D"}]}
     * @param string $work
     */
    public function userList($work='init'){
        if(!$this->isOpPermit('R')){
            echo "权限不足";
            return;
        }
        switch ($work){
            case 'init':    //初始化显示页面
                $this->baseAssign();
                if(IsMobile()){
                    $webVar=$this->getShowVar();
                    $this->assign($webVar);
                    $this->display("userList");
                }else{
                    $this->display("userList_w");
                }
                /*
                ob_end_clean();
                ob_implicit_flush(1);
                echo "rrerererer";
                sleep(5);
                */
                break;
            case 'show':   //PC真正的工作页面
                $this->baseAssign();
                $webVar=$this->getShowVar();
                $this->assign($webVar);
                $this->display("userList");
                break;
            case 'getList': //取用户列表数据
                $this->getListJson();
                break;
            case "upload":  //上传含有批量用户信息的execl文件
                $this->uploadJson();
                break;
            case "sort":
                $order=array($_POST['field']=>$_POST['order']);
                condition::save($order,'ORDER_ORGUSERLIST');  //保存排序条件
                break;
            case "destroy":
                $this->destroyUser();
                break;
            case "update":
                $this->updateRecord();
                break;
            case "save":
                break;
        }

    }

    /**
     * 生成查询条件，以及相关显示内容
     */
    private function getShowVar(){
        $conTpl=array('agent'=>0,'account'=>'','realname'=>'','phone'=>'','company'=>'','groups'=>'');
        $cond=$this->getRec($conTpl,false); //查询条件数组
        $webVar=$cond;  //前端显示数据数组
        $cond=arrayZip($cond,array(null,0,'不限','0','','全部'));	//清除没意义的条件
//dump($_POST);

        $viewAll=$this->isOpPermit('A');    //是否具有管理所有用户的权限
        //$viewAll=false;
        $webVar['fixOrg']= ($viewAll)?'false':'true';    //前端能否修改机构条件
        $webVar['canUpdate']=($this->isOpPermit('U'))?'true':'false';
        $webVar['canCreate']=($this->isOpPermit('C'))?'true':'false';
        $webVar['canDestroy']=($this->isOpPermit('D'))?'true':'false';
//var_dump($viewAll);

        //处理机构查询条件
        $dbUser=D('User');
        $currentUser=$dbUser->where('id='.$this->userId())->find();
        $currentAgent=(empty($currentUser['agent']))?-1:$currentUser['agent'];  //无法取得当前用户是那个机构的把当前用户机构定为不可能的编码
        if(true==$viewAll){
            if(isset($_POST['agent'])){
                $cond['agent']=intval($_POST['agent']);
            }else{
                $cond['agent']=$webVar['agent']=$currentAgent;
            }
        }else{
            //只能管理当前操作员所在的机构
            $cond['agent']=$currentAgent;
            $webVar['agent']=$currentAgent;
        }
        $cond=arrayZip($cond,array(null,0,'不限','0','','全部'));	//清除没意义的条件
        //处理工作单位模糊查询
        if(!empty($cond['company'])){
            $cond['company']=array('like','%'.$cond['company'].'%');
        }
        $order=array("account"=>"asc");
        condition::save($order,'ORDER_ORGUSERLIST');  //保存排序条件
        condition::save($cond,'COND_ORGUSERLIST');  //保存查询条件
        condition::save(-1,'TOTAO_ORGUSERLIST');    //通知重新计算命中记录总数

        //供显示的机构列表数据
        $dbAgent=D('agent');
        $agentList=$dbAgent->getNameList();
        $webVar['agentList']=str_replace('"',"'",json_encode2(array_values($agentList)));
        $webVar['fieldName']=$dbAgent->getUserFieldName($webVar['agent']);
        $webVar["importableFields"]=$dbAgent->getUserImportableFieldsName($webVar['agent']);
//dump($webVar);
        setPara("WEBVAR_ORGUSERLIST",$webVar);
        return $webVar;
    }

    /**
     * 执行分页查询，输出用户列表数据
     */
    private function getListJson(){
        $cond=condition::get('COND_ORGUSERLIST');
        $order=condition::get('ORDER_ORGUSERLIST');
        $dbUser=D('User');
        $page=$_POST['page'];
        $rows=$_POST['rows'];
        $recs=$dbUser->getList($cond,$order,'',$page,$rows);
        if(!is_array($recs)) $recs=array();
        $result["rows"]=$recs;
        $total=condition::get('TOTAO_ORGUSERLIST');
        if($total<=0) {
            //重新计算命中记录总数
            $total=$dbUser->numbers($cond);
            condition::save($total,'TOTAO_ORGUSERLIST');    //缓存翻页时可不重新计算
        }
        $result["total"]=$total;
        if(null==$result)	echo '[]';
        else echo json_encode2($result);
    }

    private function uploadJson(){
        $t=$_REQUEST['t'];
        //var_dump($_FILES);
        $upload = new FileUpload();

        //处理上传文件
        try {
            $uparray = $upload->BeginUpload2($t, array('xls','xlsx','txt'),500*1024);
            //var_dump($uparray);
            $tmpFile=$uparray[0]['tmp_name'];   //上传到服务端的文件及路径
            $orgFileName=$uparray[0]['name'];   //被上传源文件名
            $objExcel = PHPExcel_IOFactory::load($tmpFile);
            //$objPHPExcel->setActiveSheetIndex(0);
            $sheetData = $objExcel->getSheet(0)->toArray(null,true,true,true);

            //分析第一行
            $organization=json_decode($sheetData[1]['A'],true);
            $agentid=$organization["id"];
            if(count($organization)!=2||$agentid<1) throw new Exception("第一行数据格式错误！");
            $dbAgent=D('agent');
            $organizationName=$dbAgent->where("id=".$agentid)->getField("name");
            if(null==$organizationName||$organizationName!=$organization['name']) throw new Exception("第一行数据错误！");

            $webVar=getPara("WEBVAR_ORGUSERLIST");
            if(count($webVar)<6) throw new Exception("非法上传！");

            if($webVar['fixOrg']=='true' && $webVar['agent']!=$agentid) throw new Exception("您无权上传此机构的数据。");

            //检查用户数限制
            $maxRow = count($sheetData);
            $dbUser=D("user");
            $attr=getExtAttr($dbAgent,"id=".$agentid,$attrName="config",$field='config');
            $userlimit=intval($attr["userlimit"]);
            if($userlimit>0){
                $userNumbers=$dbUser->where("agent=".$agentid)->count();
                $userNumbers=intval($userNumbers);
                if($userNumbers+$maxRow-2 > $userlimit) throw new Exception("机构用户数已超过限制:".$userlimit);
            }


            //分析第二行获取上传字段表
            $importableFields=$dbAgent->getUserImportableFieldsName($agentid);
            $importFields=array();  //文件头中读到的导入字段，key为字段名，value为xls文件的列号
            foreach ($sheetData[2] as $col=>$name){
                if(!empty($importableFields[$name])) $importFields[$importableFields[$name]]=$col;
            }
            if(empty($importFields["account"] || empty($importFields["password"])) ) throw new Exception("用户账号及密码必须导入");

            //逐行处理导入的数据
            $prg=new ProgressAction();
            $prg->clearMsg();
            $prg->putMsg("导入文件：".$orgFileName);
            $prg->putMsg("机构信息：".$sheetData[1]['A']);

//var_dump($importFields);
            $imported=0;    //记录成功导入的记录数
            $falseCounter=0;

            for($row = 3; $row <= $maxRow; $row++) {
                unset($record);
                $record=array("agent"=>$agentid);   //这里已经验证了agentID是存在的
                foreach ($importFields as $field=>$col){
                    $record[$field]=$sheetData[$row][$col];
                    if(null===$record[$field]) $record[$field]="";
                }
                //对读入的一条记录进行处理
                try{
                    $record['password']=$dbUser->encryptPassword($record['password']);
                    $dbUser->validate($record);
                    $dbUser->adduser($record);
                    $imported++;
                }catch (Exception $ex){
                    $prg->putMsg(sprintf("%4d行：账号=%s, 出错：%s",$row,$record["account"],$ex->getMessage()));
                    $falseCounter++;
                }
            }
            $prg->putMsg(sprintf("成功导入 %d 条记录，失败 %d 条。",$imported,$falseCounter));

        }
        catch(Exception $e)  {
            echo '{"retcode":"false", "message":"'.$e->getMessage().'"}';
            exit;
        }

        $data=array("retcode"=>"true","url"=>U("Progress/getMsgAjax"));
        echo json_encode2($data);
    }

    /**
     * 向记录导入结果的session变量增加一行数据---作废
     * @param $msg
     * @param bool $newline
     */
    private function importResultLog($msg,$newline=true){
        $str=$msg;
        if($newline) $str .="\n";
        $_SESSION['USER_IMPORTRESULT'] .= $str;
    }

    /**
     * 按POST过来的记录字段更新记录
     */
    private function updateRecord(){
        $id=intval($_POST['id']);
        try{
            if($id<1) throw new Exception("缺少参数ID");

            $record=array();
            if(!empty($_POST["username"])) $record["username"]=$_POST["username"];
            if(!empty($_POST["status"])) $record["status"]=$_POST["status"];
            $modifiableFields=array("phone","idcard","company","realname","groups");
            foreach ($modifiableFields as $key){
                if(isset($_POST[$key])) $record[$key]=$_POST[$key];
            }
            $dbUser=D("user");
            $rt=$dbUser->where("id=".$id)->save($record);
            if(false===$rt) throw new Exception("更新失败");
            echo json_encode2($record);
        }catch (Exception $e){
            echo json_encode2(array('isError' => true,'msg' => $e->getMessage() ));
            return;
        }
    }

    /**
     * 删除用户及其相关的资料
     */
    private function destroyUser(){
        $id=intval($_POST['id']);
        try{
            if($id<1) throw new Exception("缺少参数ID");
            if($id==$this->userId()) throw new Exception("不能删除自己!");
            $dbUser=D("user");
            $result=$dbUser->deleteUser($id);
            if(false==$result) throw new Exception("删除失败，注意播主不能删除。");
            else echo json_encode2(array(	'success' => true));
        }catch (Exception $e){
            echo json_encode2(array('isError' => true,'msg' => $e->getMessage() ));
            return;
        }
    }
}