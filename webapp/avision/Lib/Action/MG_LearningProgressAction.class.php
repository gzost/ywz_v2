<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2020/8/31
 * Time: 15:43
 * 学习进度一览表
 */

require_once COMMON_PATH.'AdminBaseAction.class.php';
require_once(LIB_PATH.'Model/AgentModel.php');
require_once APP_PATH.'../public/exportExecl.php';

class MG_LearningProgressAction extends AdminBaseAction{
    const   CACHE_agentlist="LP_agentlist";    //定义缓存机构列表数据的session变量名
    const   CACHE_header="LP_header";       //缓存表头
    const   CACHE_data="LP_data";       //缓存数据

    const   max_chnNumbers=20;      //列表能一次列出的最大课程数据

    private $params=array();    //传递到子程序的参数

    /**
     * 本类对外的公共接口
     * 权限"operation":[{"text":"查看自己所在机构","val":"R"},{"text":"查看所有机构","val":"A"},{"text":"填充用户信息","val":"F"}]
     */
    public function main(){
        session_start();
        $this->params['uid'] = $this->userId();
        $this->params["agent"] = $this->getUserInfo("agent"); //当前用户所在机构
        $this->params["rightA"] = $this->isOpPermit("A"); //可管理所有频道[true|false]
        $this->params["rightF"] = $this->isOpPermit("F"); //可填充用户信息[true|false]
        if(empty($_POST["func"])) $func="init";
        else {
            if($_POST["contextToken"] != session_id())    die("非法访问！");
            else $func=$_POST["func"];
        }
        $this->$func(); //根据请求调用不同的方法
    }

    /**
     * 显示查询条件
     */
    private function init(){
        unsetPara(self::CACHE_agentlist, self::CACHE_header, self::CACHE_data);   //清除缓存
        $this->showList("init");
    }

    /**
     * @param string $opt   ="init" 时进行初始化页面，="search"根据POST的查询条件查询数据并缓存到session变量中
     */
    private function showList($opt="search"){
        $this->baseAssign();
        $condTpl=array("agent"=>0, "chnName"=>"", "bDate"=>date("Y-m-d", strtotime("-1 year")), "eDate"=>date("Y-m-d"));
        $webVar=ouArrayReplace($condTpl, $_POST);

        if(empty($webVar["agent"])) $webVar["agent"]=$this->params["agent"]; //未选择机构用自身的机构
        $webVar["agentReadonly"]=($this->params["rightA"])?"false":"true";    //是否可查询其它机构
        $webVar["fillUserInfo"]=($this->params["rightF"])?"true":"false";    //是否可填充用户信息

        //取机构列表
        $agentListJson=getPara(self::CACHE_agentlist);
        if(empty($agentListJson)){
            $dbAgent=D("agent");
            $agentList=$dbAgent->getNameList();
            $agentListJson=str_replace('"',"'",json_encode2($agentList));
            setPara(self::CACHE_agentlist,$agentListJson);  //缓存机构列表
        }
        $webVar['agentListJson']=$agentListJson;

        $webVar["contextToken"]=session_id();
        $webVar["msg"]="";
        $webVar["showData"]="0";
        $webVar["header"]=array();

        //是否执行查询
        if($opt=="search") {
            try {
                //1、查找符合条件的频道
                $chnList = $this->getChnList($webVar);    //取频道列表
//dump($chnList);
                $chnNumbers = count($chnList);    //命中的频道数
                if ($chnNumbers > self::max_chnNumbers) throw new Exception("查询的课程数量过多：" . $chnNumbers);
                $header = $this->genHeader($chnList);
                setPara(self::CACHE_header, $header);    //缓存表头

                //2、查询数据
                $data = $this->search($webVar, $chnList);
                setPara(self::CACHE_data, array_values($data));
                $webVar["showData"] = "1";    //请求显示数据标志

            } catch (Exception $e) {
                $webVar["msg"] .= $e->getMessage();
            }
        }
        session_commit();
        $webVar["header"]=$header;
        $this->assign($webVar);
        $this->display("showList");
    }

    private function search($webVar,$chnList){
        //1、获取频道id字串
        $key=array_keys($chnList);
        $chnids=implode(",",$key);
//dump($chnids);
        $btime=(empty($webVar["bDate"]))? "1000-01-01":$webVar["bDate"];    //开始时间
        $etime=(empty($webVar["eDate"]))? "6000-12-31":$webVar["eDate"]." 23:59:59";    //截止时间

        //2、统计用户观看时长，并提取用户信息
        $Model = new Model();
        $sql="select S.userid,S.chnid,account,username,phone,realname,company, sum(duration) duration from __PREFIX__statchannelviews as S 
              left join __PREFIX__user as U on U.id=S.userid 
              where S.chnid in ($chnids) and ( S.rq between '$btime' and '$etime') group by S.userid,S.chnid order by company";
        $sql=str_replace("__PREFIX__",C("DB_PREFIX"),$sql);
        $duration=$Model->query($sql);
//echo $Model->getLastSql(),"==",count($duration),"<br>";
//dump($duration);

        //3、查询作业提交情况
        $sql="select A.userid, E.chnid,count(*) ans from __PREFIX__answer as A inner join __PREFIX__exercise as E on E.id=A.exerciseid
            where E.chnid in ($chnids) and (answertime between '$btime' and '$etime') group by A.userid, E.chnid";
        $sql=str_replace("__PREFIX__",C("DB_PREFIX"),$sql);
        $ans=$Model->query($sql);
//echo $Model->getLastSql(),"==",count($ans),"<br>";
//dump($ans);
        //3.1增加key=userid_chnid, 方便合并查询
        foreach ($ans as $k=>$v){
            $ans[$v["userid"]."_".$v["chnid"]]=$v;
            unset($ans[$k]);
        }
//dump($ans);

        //4、整理数据
        $data=array();  //输出符合表格格式的数据，$key为用户ID
        foreach ($duration as $key=>$row){
            $userid=$row["userid"];
            if(!isset($data[$userid])){
                //还没记录此用户的数据新建
                $data[$userid]=$row;
            }
            //ans,dur,rate
            $data[$userid]["dur".$row["chnid"]]=$row["duration"];
            $count=intval($ans[$row["userid"]."_".$row["chnid"]]);  //取提交作业次数
            $data[$userid]["ans".$row["chnid"]]=$count;

            $scoreAns=($count >0)? 0.3: 0;  //作业得分
            $classHours=intval($chnList[$row["chnid"]]["classHours"]);
            $scoreDur=($classHours !=0 )? $row["duration"]/$classHours*0.7 : 0.7;   //学习时长得分
            if($scoreDur>0.7) $scoreDur=0.7;
            $data[$userid]["rate".$row["chnid"]] = round(($scoreAns+$scoreDur)*100,0);
        }
//dump($data);
        return $data;
    }

    /**
     * 生成表头
     * 列名按以下定义：作业提交,观看时长,完成率.分别问ans,dur,rate+<频道ID>
     * @param $chnList
     * @return array
     */
    private function genHeader($chnList){
        $header=array(
            0=>array(array("name"=>"userid", "text"=>"用户号", "data-options"=>"rowspan:2", "rowspan"=>2),
                array("name"=>"account", "text"=>"账号", "data-options"=>"rowspan:2", "rowspan"=>2),
                array("name"=>"username", "text"=>"昵称", "data-options"=>"rowspan:2", "rowspan"=>2),
                array("name"=>"phone", "text"=>"手机号码", "data-options"=>"rowspan:2", "rowspan"=>2),
                array("name"=>"realname", "text"=>"姓名", "data-options"=>"rowspan:2", "rowspan"=>2),
                array("name"=>"company", "text"=>"工作单位", "data-options"=>"rowspan:2", "rowspan"=>2),
            ),
            1=>array()
        );
        foreach ($chnList as $chid=>$rec){
            $header[0][]=array("name"=>"", "text"=>$rec["name"], "data-options"=>"colspan:3, width:240", "colspan"=>3);
            $header[1][]=array("name"=>"ans".$chid, "text"=>"作业提交","data-options"=>"align:'center'");
            $header[1][]=array("name"=>"dur".$chid, "text"=>"观看时长","data-options"=>"align:'center'");
            $header[1][]=array("name"=>"rate".$chid, "text"=>"完成率%","data-options"=>"align:'center'");
        }
        return $header;
    }

    /**
     * 取符合条件的频道列表，频道属性中未设置classHours或=0的频道将忽略；若设置了课程时间范围，不在查询时间范围内的课程也被忽略
     * @param $webVar   包含查询条件
     * @return mixed    null-没有符合条件的记录，以记录id为key的记录数组
     * @throws Exception
     */
    private function getChnList($webVar){
        if(empty($webVar["agent"])) throw new Exception("必须指定机构");
        $cond=array("agent"=>$webVar["agent"]);
        if(!empty($webVar["chnName"])){
            $cond["name"]=array("like","%".trim($webVar["chnName"])."%");
        }

        $dbChn=D("channel");
        $records=$dbChn->where($cond)->getField("id,name,attr");
//echo $dbChn->getLastSql();
        foreach ($records as $key=>$rec){
            $attr=json_decode($rec['attr'],true);

            if(empty($attr["classHours"])   //未设置classHours或=0的频道将忽略
                || (!empty($attr["termBeginDate"]) && !empty($webVar["eDate"]) && ($attr["termBeginDate"]>$webVar["eDate"]))    //课程开始时间在查询时间范围之后
                || (!empty($attr["termEndDate"]) && !empty($webVar["bDate"]) && ($attr["termEndDate"]<$webVar["bDate"]))    //课程结束时间在查询时间范围之前
            ) unset($records[$key]);
            else {
                $records[$key]["classHours"]=$attr["classHours"];
                unset($records[$key]["attr"]);  //这里的数据比较大，尽快释放
            }
            unset($attr);
        }
        if(empty($records)) throw new Exception("没有符合条件的频道");
        return $records;
    }

    //按datagrid格式输出数据
    private function loadDataJson(){
        //echo "[]"; return;
        $rows=getPara(self::CACHE_data);
        $total=count($rows);
        $page=$_POST["page"]-1;
        $length=$_POST["rows"];
        $rows=array_slice($rows,$page*$length,$length);
        if(empty($rows)) echo "[]";
        else{
            $result=array("total"=>$total,"rows"=>$rows);
            echo json_encode2($result);
        }
        session_commit();
    }

    //从cache中读出数据并下载到本地
    private function saveExcel(){
        $condTpl=array("agent"=>0, "chnName"=>"", "bDate"=>date("Y-m-d", strtotime("-1 year")), "eDate"=>date("Y-m-d"));
        $webVar=ouArrayReplace($condTpl, $_POST);

        $result=array();
        $result['header']=getPara(self::CACHE_header);
        $result['title'][]=array('text'=>$webVar['bDate'].'至'.$webVar['eDate'].'学习进度一览表','size'=>16);
        $result["rows"]=getPara(self::CACHE_data);
        $result["total"]=count($result["rows"]);
        $result['defaultFile']='学习进度一览表.xlsx';
//dump($result);
        exportExecl($result);
    }

    //扫描指定机构所属频道的频道会员表，在会员信息中查找用户phone,idcard,company,realname等信息，若用户对应的信息是空白则填入。
    private function fillUserInfo(){
        $condTpl=array("agent"=>0, "chnName"=>"", "bDate"=>date("Y-m-d", strtotime("-1 year")), "eDate"=>date("Y-m-d"));
        $webVar=ouArrayReplace($condTpl, $_POST);

        OUwrite("====智能用户信息填充====<br><br>");
        try{
            $agent=intval($webVar["agent"]);
            if(empty($agent)) throw new Exception("必须指定机构");
            $chnRec=D("channel")->where("agent=$agent")->getField("id,id");
            $chnIdList=implode(",",array_keys($chnRec));
            OUwrite("机构频道：$chnIdList<br>");

            OUwrite("查找有空白信息的用户...");
            $sql="select C.uid,C.note,U.account,phone,idcard,company,realname from __PREFIX__channelreluser as C 
                left join __PREFIX__user as U on U.id=C.uid where C.chnid in($chnIdList) and (phone='' or idcard='' or company='' or realname='') order by C.uid";
            $sql=str_replace("__PREFIX__",C("DB_PREFIX"),$sql);
            $Model = new Model();
            $userArr=$Model->query($sql);
            $total=count($userArr);
            OUwrite("有 $total 条记录需要处理。<br>");

            $currentUser=array();  //记录当前在处理的用户，出现新用户时检查是否可更新字段，无轮是否更新均删除此变量
            $updateFields=array();  //已经更新的字段，key=字段名，value=字段值
            foreach ($userArr as $key=>$row){
                $uid=$row["uid"];
                $account=$row["account"];

                if($row["uid"]!=$currentUser["uid"]){
                    //出现下一个要处理的用户
                    if(count($updateFields) >0 ){
                        //有可更新的字段
                        dump($updateFields);
                        //dump($currentUser["uid"]);
                        $dbUser=D('user');
                        $rt=$dbUser->where("id=".$currentUser["uid"])->save($updateFields);
                        echo $dbUser->getLastSql();
                        OUwrite("updated($rt)<br>");
                    }else{
                        OUwrite("no update<br>");
                    }

                    OUwrite("<br>正在处理：($uid)$account...");
                    $currentUser=$row;
                    $updateFields=array();
                }
                //继续处理当前用户，分析会员注册资料提取有用信息
                OUwrite(".");
                if(count($updateFields)>=4) continue;   //可更新的字段都填写了，直接跳过
                $info=json_decode($row["note"],true);
                //dump($info);
                foreach ($info as $attr){
                    $quest=str_replace(array(' ','*',':','　','：'),'',$attr['quest']);   //过滤多余的字符
                    $quest=str_replace(array('姓名','真实姓名'),'realname',$quest);   //统一名称
                    $quest=str_replace(array('身份证号','身份证号','证件号'),'idcard',$quest);   //统一名称
                    $quest=str_replace(array('电话','电话号码','手机'),'phone',$quest);   //统一名称
                    $quest=str_replace(array('学校全称','学校','单位'),'company',$quest);   //统一名称
                    //OUwrite($quest."=");
                    if((strpos("phone,idcard,company,realname,",$quest.',')!==false) && empty($currentUser[$quest]) && empty($updateFields[$quest] )){
                        $answer=trim($attr['answer']);
                        if(!empty($answer))  $updateFields[$quest]=$answer;
                    }

                }
            }
            OUwrite("<br>");
        }catch (Exception $e){
            OUwrite($e->getMessage());
        }

        OUwrite("<br><br>==填充完成==<br>");
    }
}