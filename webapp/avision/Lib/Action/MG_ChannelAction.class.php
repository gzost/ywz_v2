<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2018-7-3
 * Time: 21:46
 */
require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/ChannelreluserModel.php');
require_once APP_PATH.'../public/FileUpload.Class.php';
require_once LIB_PATH."Action/ProgressAction.class.php";
/** Include PHPExcel */
require_once C('PHPExeclPath').'PHPExcel.php';

class MG_ChannelAction extends AdminBaseAction
{
    public function main(){
        if(IsMobile()) $this->setting();
        else{
            $this->baseAssign();
            $this->display();
        }

    }

    /**
     * 频道综合管理PC/手机公共界面，根据权限显示查询界面或直接跳到频道列表
     * 权限：{"operation":[{"text":"查看自己频道","val":"R"},{"text":"查看所有频道","val":"A"},{"text":"平台管理操作","val":"P"}]}
     */
    public function setting(){
        $webVarTpl=array('chnId'=>'', 'chnName'=>'', 'account'=>'', 'work'=>'init');
        $webVar=getRec($webVarTpl,false);

        $webVar['showCond']=$mgrAll=$this->isOpPermit('A');     //是否可管理所有频道
        $webVar['platformOpt']=$platformOpt=$this->isOpPermit('P');    //是否可进行平台操作
//var_dump($mgrAll,$platformOpt);

        if(false==$mgrAll){
            //只查看自己的频道
            $this->channelListSetVar();
            $webVar['channelListHtml']=$this->fetch('channelList');
        } else {
            $webVar['channelListHtml']='';
        }
//var_dump( $webVar['channelListHtml']);
        $this->assign($webVar);
        $this->display('setting');
    }

    /**
     * 查询并输出频道列表
     * @param int $chnId
     * @param string $chnName
     * @param string $account
     * @param int $owner
     */
    public function channelList($chnId=0,$chnName='',$account='',$owner=0){
        $this->channelListSetVar($chnId,$chnName,$account,$owner);
        $this->display('channelList');
        return;
     }
    protected function channelListSetVar($chnId=0,$chnName='',$account='',$owner=0){
        $mgrAll=$this->isOpPermit('A');     //是否可管理所有频道
        $webVar=array();
        $cond=array();
//var_dump($mgrAll);
//echo '====='; die('dddd');
        if(false == $mgrAll){
            $cond['owner']=$this->userId();
            if(null==$cond['owner']) $cond['owner']=-1;
        }elseif(strlen($account)>0){
            $dbUser=D('user');
            $userId=$dbUser->where(array('account'=>array('like','%'.$account.'%')))->field('id')->select();
//echo $dbUser->getLastSql();
            if(null!=$userId) $cond['owner']=array('in',result2string($userId,'id'));
            else $cond['owner']=-1; //找不到播主，赋值一个不可能条件
        }
        $chnId=intval($chnId);
        if(0<$chnId) $cond['id']=$chnId;
        if(0==$chnId && strlen($chnName)>0) $cond['name']=array('like','%'.$chnName.'%');
//dump($cond);
        $dbChannel=D('channel');
        $chnList=$dbChannel->where($cond)->field('id,name,owner')->order('owner')->select();
        $webVar['chnList']=(null==$chnList)?array():$chnList;
//dump($chnList);
//echo $dbChannel->getLastSql();
        $this->assign($webVar);
    }
    /**
     * 修改相关频道的设置，保存时也调用此函数
     * @param int $chnId
     * @param string $chnName
     * @param int $owner
     * @param string $func  设置的功能名称，本函数将调用同名函数进行针对性处理，也调用同名的显示模板，因此每增加一个设置功能需要增加一个function及一个TPL
     */
    public function modifySetting($chnId=0,$chnName='',$owner=0,$func=''){
        $webVar=array('chnId'=>$chnId, 'chnName'=>$chnName, 'owner'=>$owner);
        $webVar['mgrAll']=$mgrAll=$this->isOpPermit('A');     //是否可管理所有频道
        $webVar['platformOpt']=$platformOpt=$this->isOpPermit('P');    //是否可进行平台操作
        $this->$func($webVar);
//dump($_REQUEST);

        $this->assign($webVar);
        $this->display($func);
    }

    /**
     * 设置选用的模块及功能
     * @param $webVar
     */
    private function set_module(&$webVar){
        if(isset($_POST['rows'])){
            //保存
            $webVar['msg']="";
            $tabs=$extFuncs=array();
            $activetab=101;
            foreach ($rows=$_POST['rows']['rows'] as $row){
                if($row['val']<500){
                    //tabs配置
                    if($row['use']=='Y'){
                        $tabs[]=array('val'=>$row['val'], 'text'=>$row['text'], 'order'=>$row['order']);
                        if($row['default']=='Y') $activetab=$row['val'];
                    }
                }elseif($row['val']<600){
                    //扩展功能配置
                    if($row['use']=='Y') {
                        $extFuncs[]=array('val'=>$row['val'], 'text'=>$row['text'], 'order'=>$row['order']);
                    }
                }
            }
            $db=D('channel');
//dump($extFuncs);
            $rt=$db->setTabs($webVar['chnId'],$tabs,$activetab,$extFuncs);
            if(false===$rt) $webVar['msg']="保存失败";
            else  $webVar['msg']="保存成功";
//dump($activetab);
        }else{
            $webVar['msg']="";
        }
        $db=D('channel');
        $tabRecs=$db->getTabs4Edit($webVar['chnId']);
//dump($tabRecs);
        $webVar['tabJson']=json_encode2(array_values($tabRecs));
//dump($webVar['tabJson']);
        return;
    }

    //在播放模块需要注册会员时会调用，需要设成public
    public function set_registe(&$webVar){
        //读频道信息
        $chnid=$webVar['chnId'];
        $dbchn=D('channel');
        $chnAttr=$dbchn->getAttrArray($chnid);
        if(isset($_POST['rows'])){
            $quest=$answer=array();
            foreach ($rows=$_POST['rows']['rows'] as $row){
                $quest[]=strip_tags($row['quest']); //清除HTML标签
                $answer[]=strip_tags($row['answer']);
            }
            $chnAttr['signQuest']=$quest;
            $chnAttr['signQuestAns']=$answer;
            $chnAttr['signNote']=(strlen($_POST['signNote'])>2)?strip_tags($_POST['signNote']):'请回答以下问题';   //不允许HTML标签
            $chnAttr['signpass']=('true'==$_POST['signpass'])?'true':'false';
            $data=array('attr' => json_encode2($chnAttr));
//dump($data['attr'] );
            $ret = $dbchn->where(array('id'=>$chnid))->save($data);
        }
//var_dump(true==$_POST['signpass']);
//dump($chnAttr);
        $webVar['signpass']=$chnAttr['signpass'];
        $webVar['signNote']=$chnAttr['signNote'];

        $qna=array();
        foreach ($chnAttr['signQuest'] as $k=>$v){
            if(isset($chnAttr['signQuestAns']) && null!=$chnAttr['signQuestAns'][$k]) $ans=$chnAttr['signQuestAns'][$k];
            else $ans='';
            $qna[]=array('quest'=>$v, 'answer'=>$ans);
        }
        $webVar['tabJson']=json_encode2($qna);
//dump($webVar);
        return;
    }

    //从其它频道同步会员或批量导入
    private function syn_members(&$webVar){
        //dump($webVar);
        $work=$_REQUEST['work'];    //上传文件只能get过来
        //执行同步操作
        if("sync"==$work){
            $this->syn_members_doSync($webVar); //执行同步操作
            exit;
        }elseif ("import"==$work){
            $this->syn_members_doImport($webVar);
            exit;
        }

        //初始页面，选择源频道
        //保存当前频道资料，共文件导入使用
        setPara("CURRENT_CHANNEL_INFO",$webVar);
        //取与当前频道相同owner的频道列表
        $dbChannel=D('channel');
        $cond=array("owner"=>$webVar["owner"]);
        $chnList=$dbChannel->where($cond)->field('id,name')->order('name')->select();
        $chnList=(null==$chnList)?array():$chnList;
        $webVar['chnList']=str_replace('"',"'",json_encode2($chnList));
        $webVar['soureChnId']=$chnList[0]['id'];
        //dump($webVar);
        return;
    }

    private function syn_members_doSync($webVar){
        $dbChannel=D('channel');
        $dbChnRelUsr=D("Channelreluser");
        try{
            $soureChnId=$_POST['soureChnId'];   //源频道ID
            if(1>$soureChnId) throw new Exception("缺少源频道参数");
            $sync2source=(empty($_POST["sync2source"]))?false:true; //是否同步到源频道
            $chnId=$_POST["chnId"];
            if(1>$chnId) throw new Exception("缺少当前频道参数");
            $owner=$_POST["owner"];
            if(1>$owner) throw new Exception("缺少频道主参数");
            if($soureChnId == $chnId) throw new Exception("相同频道无需同步。");

            //取源频道主
            $sourceOwner=$dbChannel->where("id=".$soureChnId)->getField("owner");
            if($owner != $sourceOwner) throw new Exception("只能同步相同播主的频道！");

            //开始从源频道同步会员
            //echo "开始从源频道同步会员<br>";
            $cond=array("chnid"=>$chnId);
            $targetMaxId=$dbChnRelUsr->where($cond)->Max("id");   //目标频道当前最大记录ID，目的是反向同步时，不用考虑之后的记录
            $sql = "insert into __PREFIX__channelreluser(chnid,uid,`type`,status,begindate,enddate,note,classify,note2) ";
            $sql.= "select $chnId,A.uid,A.`type`,status,A.begindate,A.enddate,A.note,A.classify,A.note2 from __PREFIX__channelreluser A left join";
            $sql.= " (select uid  from __PREFIX__channelreluser where chnid= $chnId and `type`='会员') as B";
            $sql.= " on A.uid=B.uid where A.chnid=$soureChnId and B.uid is NULL and A.`type`='会员' and A.status='正常' ";
            $sql=str_replace("__PREFIX__",C("DB_PREFIX"),$sql);
            $db=new Model();
            $result=$db->execute($sql);
            //echo $db->getLastSql();
            echo "从选择的频道中同步了 $result 条会员记录到当前频道中。<br>";
            if($sync2source){
                //echo "同步会员到源频道<br>";
                $sql = "insert into __PREFIX__channelreluser(chnid,uid,`type`,status,begindate,enddate,note,classify,note2) ";
                $sql.= "select $soureChnId,A.uid,A.`type`,status,A.begindate,A.enddate,A.note,A.classify,A.note2 from __PREFIX__channelreluser A left join";
                $sql.= " (select uid  from __PREFIX__channelreluser where chnid= $soureChnId and `type`='会员') as B";
                $sql.= " on A.uid=B.uid where A.chnid=$chnId and A.id<=$targetMaxId and B.uid is NULL and A.`type`='会员' and A.status='正常' ";
                $sql=str_replace("__PREFIX__",C("DB_PREFIX"),$sql);
                $db=new Model();
                $result=$db->execute($sql);
                echo "从当前频道同步了 $result 条会员记录到选择的频道中。<br>";
            }
            echo "<br>同步完成<br>";
        }catch (Exception $e){
            echo $e->getMessage();
        }
        exit;
    }

    private function syn_members_doImport(){
        $webVar=getPara("CURRENT_CHANNEL_INFO");    //读入当前的频道信息
        //dump($webVar);
        $dbChnUsr=D("channelreluser");
        $dbUser=D("user");
        $upload = new FileUpload();
        try{
            if($webVar["chnId"] < 1 || strlen($webVar["chnName"])<3 || $webVar["owner"]<1) throw new Exception("缺少当前频道信息");
            if($this->userId()!=$webVar['owner'] && true!=$webVar['mgrAll']) throw new Exception("您无权导入此频道的数据。");

            $uparray = $upload->BeginUpload2("users", array('xls','xlsx','txt'),500*1024);
            //var_dump($uparray);
            $tmpFile=$uparray[0]['tmp_name'];   //上传到服务端的文件及路径
            $orgFileName=$uparray[0]['name'];   //被上传源文件名
            $objExcel = PHPExcel_IOFactory::load($tmpFile);
            //$objPHPExcel->setActiveSheetIndex(0);
            $sheetData = $objExcel->getSheet(0)->toArray(null,true,true,true);

            //分析第一行
            $chnInfo=json_decode($sheetData[1]['A'],true);
            $chnid=$chnInfo["id"];
            if(count($chnInfo)!=2||$chnid<1 || strlen($chnInfo["name"])<3 ) throw new Exception("第一行数据格式错误！");
            if($chnid != $webVar["chnId"] || $chnInfo["name"]!=$webVar["chnName"]) throw new Exception("文件的频道信息与当前频道不匹配");

            //分析第二行获取上传字段表
            $importableFields=$dbChnUsr->getImportableFieldsName();
            $importableFields["账号"]="account";
            $importFields=array();  //文件头中读到的导入字段，key为字段名，value为xls文件的列号
            foreach ($sheetData[2] as $col=>$name){
                if(!empty($importableFields[$name])) $importFields[$importableFields[$name]]=$col;
            }
//var_dump($importableFields,$importFields);
            if(empty($importFields["account"] ) ) throw new Exception("用户账号必须导入");

            //逐行处理导入的数据
            $prg=new ProgressAction();
            $prg->clearMsg();
            $prg->putMsg("导入文件：".$orgFileName);
            $prg->putMsg("频道信息：".$sheetData[1]['A']);

//var_dump($importFields);
            $maxRow = count($sheetData);    //excel的行数
            $imported=0;    //记录成功导入的记录数
            $falseCounter=0;
            for($row = 3; $row <= $maxRow; $row++) {
                unset($record);
                $record=array("chnid"=>$chnid,"type"=>"会员","status"=>"正常","enddate"=>"6999-12-31");
                foreach ($importFields as $field=>$col){
                    $record[$field]=trim($sheetData[$row][$col]);
                }
                //对读入的一条记录进行处理
                try{
                    $account=$record['account'];
                    if(empty($record['account'])) throw new Exception("账号错误");
                    unset($record["account"]);
                    $uid=$dbUser->getUserId($account);
                    if($uid<1) throw new Exception("系统无此账号");
                    $record["uid"]=$uid;

//var_dump($tt,$record["type"]);
                    $rt=$dbChnUsr->insertRec($record);
                    if($rt<1) throw new Exception("会员已经存在");
                    $imported++;
                }catch (Exception $ex){
                    $prg->putMsg(sprintf("%4d行：账号=%s, 出错：%s",$row,$account,$ex->getMessage()));
                    $falseCounter++;
                }
            }
            $prg->putMsg(sprintf("成功导入 %d 条记录，失败 %d 条。",$imported,$falseCounter));
        }catch (Exception $e){
            echo '{"retcode":"false", "message":"'.$e->getMessage().'"}';
            exit;
        }
        $data=array("retcode"=>"true","url"=>U("Progress/getMsgAjax"));
        echo json_encode2($data);
    }

    /**
     *
     * @param $webVar 由上层函数传入包括：
     *  ["chnId"] => string(4) "1098"
     *  ["chnName"] => string(15) "admin的频道1"
     *  ["owner"] => string(1) "1"
     *  ["mgrAll"] => bool(true)
     *  ["platformOpt"] => bool(true)
     * 从web前端传入：
     *  work: =="save" 保存修改过的属性
     *  rows：被修改过的数据记录数组，每记录包括以下字段
     *      key-属性名称
     *      name-属性的显示字串
     *      gkey-属性的上级属性名称
     *      group-属性的分组显示
     *      value-属性值
     */
    private function set_attribute(&$webVar){
        $dbChannel=D("channel");
        $work=$_POST["work"];
//dump($webVar);
        if("save"==$work){
            if(is_array($_POST['rows'])){
                $rt=updateAttributes($dbChannel,array("id"=>$webVar["chnId"]),$_POST['rows']);
                if(false===$rt) Oajax::errorReturn('更新失败');
                else Oajax::successReturn();
            } else Oajax::errorReturn('没有数据需要更新');
            exit;
        }

        $attrib=array(
            array("key"=>"livetime", "name"=>"开播时间", "gkey"=>"", "group"=>"播出设置", "value"=>"", "editor"=>array("type"=>"datetimebox")),
            array("key"=>"livekeep", "name"=>"播出时长(分钟)", "gkey"=>"", "group"=>"播出设置", "value"=>"", "editor"=>array("type"=>"numberbox")),
            array("key"=>"termBeginDate", "name"=>"开始日期", "gkey"=>"", "group"=>"学习周期", "value"=>"", "editor"=>array("type"=>"datebox")),
            array("key"=>"termEndDate", "name"=>"结束日期", "gkey"=>"", "group"=>"学习周期", "value"=>"", "editor"=>array("type"=>"datebox")),
            array("key"=>"classHours", "name"=>"学时(分钟)", "gkey"=>"", "group"=>"学习周期", "value"=>"", "editor"=>array("type"=>"numberbox")),
            array("key"=>"classFinish", "name"=>"学习完成后：1-提醒,2-禁止进入", "gkey"=>"", "group"=>"学习周期", "value"=>"", "editor"=>array("type"=>"numberbox")),
            array("key"=>"isbill", "name"=>"是否收费", "gkey"=>"userbill", "group"=>"收费设置", "value"=>"", "editor"=>array("type"=>"text")),
            array("key"=>"billday24", "name"=>"单日费率", "gkey"=>"userbill", "group"=>"收费设置", "value"=>"", "editor"=>array("type"=>"numberbox","options"=>array("min"=>0,"precision"=>2))),
            array("key"=>"billmonth", "name"=>"包月费率", "gkey"=>"userbill", "group"=>"收费设置", "value"=>"", "editor"=>array("type"=>"numberbox","options"=>array("min"=>0,"precision"=>2))),
            array("key"=>"billday7", "name"=>"周费率", "gkey"=>"userbill", "group"=>"收费设置", "value"=>"", "editor"=>array("type"=>"numberbox","options"=>array("min"=>0,"precision"=>2))),
            array("key"=>"billday30", "name"=>"30天费率", "gkey"=>"userbill", "group"=>"收费设置", "value"=>"", "editor"=>array("type"=>"numberbox","options"=>array("min"=>0,"precision"=>2))),
            array("key"=>"operatorIdleInt", "name"=>"播放终端最长不操作时间(秒)", "gkey"=>"player", "group"=>"播放器特性", "value"=>"900", "editor"=>array("type"=>"numberbox")),
            array("key"=>"netBrokenInt", "name"=>"网络中断最长时间(秒)", "gkey"=>"player", "group"=>"播放器特性", "value"=>"120", "editor"=>array("type"=>"numberbox")),
            array("key"=>"version", "name"=>"播放器版本[1|2]", "gkey"=>"player", "group"=>"播放器特性", "value"=>"1", "editor"=>array("type"=>"numberbox")),
            array("key"=>"spreadTarget", "name"=>"成功传播人数要求", "gkey"=>"", "group"=>"传播参数", "value"=>"0", "editor"=>array("type"=>"numberbox"))
        );
        $rt=fillExtAttr($dbChannel,array("id"=>$webVar["chnId"]),$attrib);
//var_dump($rt);
        $propertyData=str_replace('"',"'",json_encode2(array_values($attrib)));
        $webVar["propertyData"]=$propertyData;
        return;
    }
}