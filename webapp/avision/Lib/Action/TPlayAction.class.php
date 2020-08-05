<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2019/12/15
 * Time: 17:43
 * 播放界面后端
 */

require_once APP_PATH.'../public/SafeAction.Class.php';
require_once(APP_PATH.'/Common/platform.class.php');
require_once(LIB_PATH.'Model/ChannelModel.php');
require_once(LIB_PATH.'Model/ChannelreluserModel.php');
require_once(LIB_PATH.'Model/UserrelroleModel.php');
require_once(LIB_PATH.'Model/OnlineModel.php');
require_once(LIB_PATH.'Model/RecordfileModel.php');
require_once(LIB_PATH.'Model/UserModel.php');
require_once COMMON_PATH.'vod/vodBase.class.php';

class TPlayAction extends SafeAction{
    const PLAY_TOKEN="playToken";    //页面上下文校验令牌变量名称，此名称要注意与TPL中处理的页面变量名称一致

    //以下类属性由main方法初始赋值
    protected $para=null;   //读web参数形成的参数数组
    protected $chnid=0;     //频道ID
    protected $vodid=0;     //vod文件ID
    protected $dbChannel=null;  //channel数据表对象
    protected $channel=null;    //当前频道记录，attr字段已扩展到ext
    protected $dbUser=null; //用户数据表对象

    public function test(){


        dump($_SERVER["HTTP_USER_AGENT"]);
        exit;
        for($i=0; $i<10; $i++){
            echo $i.',';
            ob_flush();//修改部分
            flush();
            sleep(1);
        }
    }
    /////// 以下方法设计为通过main方法调用，其它方法调用请谨慎 ////

    /**
     * 测试频道/VOD资源等是否允许播放
     * @param $webVar   网页变量，在方法内会被修改
     * @return bool true 允许播放，false 不允许播放
     */
    private function chnAvailable(&$webVar){
        try{
            //找不到频道
            if(empty($this->channel)){
                $webVar["msg"]="频道找不到了！";
                $webVar["btnTxt"]="去首页看看";
                throw new Exception("频道找不到");
            }
            //频道关闭
            if('normal'!=$this->channel['status']){
                if('ban'==$this->channel['status']) $webVar["msg"]="频道已由平台关闭！";
                else $webVar["msg"]="频道已关闭！";
                $webVar["btnTxt"]="去首页看看";
                throw new Exception("频道已关闭");
            }
            //频道最大观众数限制
            $viewerlimit=intval($this->channel["viewerlimit"]);
            if($viewerlimit>0){
                $online=D("online")->getOnlineNum("web",$this->chnid);
                if($online>=$viewerlimit){
                    $msg="频道已经热爆了，请稍后再试。($online:$viewerlimit)";
                    $webVar["msg"]=$msg;
                    $webVar["btnTxt"]="去首页看看";
                    throw new Exception($msg);
                }
            }

            //播主是否欠费
            $userDal = D("user");
            $fee = $userDal->getAvailableBalance($this->channel['owner']);
//dump($fee); die();
            if($fee < 0) {
                //频道欠费
                $msg="播主忘记充值了，请与播主或主办方联系 $fee:".$this->channel['id'];
                $webVar["msg"]=$msg;
                $webVar["btnTxt"]="去首页看看";
                throw new Exception($msg);
            }

            //资源允许播放
            return true;
        }catch (Exception $e){
            //资源不允许播放
            return false;
        }
    }

    /**
     * 检查是否特权用户，这些用户可以在开播时间前观看频道，可以操作禁言等
     * @param $uid
     * @return bool true-是特权用户
     */
    private function isAdmin($uid){
        if($this->author->isRole('admin') || $this->author->isRole('inspector') ||
            $uid==$this->channel['owner'] || $uid==$this->channel['anchor']
        ) return true;
        else return false;
    }

    ////// main调用的方法结束

    /**
     * 播放器主入口
     * 接受参数url，在url中分析要播放的频道及VOD文件，例如：www.av365.cn/play.html?ch=1098&fl=9832
     *  - ch: 频道ID
     *  - vf: VOD记录ID
     *  - nc: 有此参数且非零、空值，则不显示频道封面，不显示系统公告，一般用于程序控制重新刷新播放器的情形
     *  - tab: 默认的活跃tabid
     *  - ag: agentID 有此参数传入时，用此覆盖用户的agentID
     *  - du: 介绍人（推荐人）ID
     * 当不提供ch或找不到ch指出的频道时，显示首页。
     * 仅提供ch时，初始化进入直播状态，若不开放VOD tab则无法切换到VOD状态
     * 提供的vf时，初始化进入VOD状态，若不开放VOD tabs则无法显示VOD列表从而切换其它VOD资源。若不开放live tab无法切换到直播状态。
     *
     *
    */
    public function main(){
        //dump($_SERVER); exit();
        //sleep(1);
        //a=explode("=","nc2");
        //var_export($a); exit();
        $webVar=array();

        //1、分析传入参数，若无传入参数或参数不包含ch，跳转到首页
        $this->author->autoIssue();		//用cookie自动登录
        $agent=$this->getUserInfo("agent");
        if(null===$agent) $agent=0;
        $url=$_REQUEST['url'];
        if(empty($url)) $this->jumpTo(U("Home/goHome",array("agent"=>$agent)));

        $this->para=$this->analyseUrl($url)["params"];    //传入的参数数组
        $this->chnid=$webVar["chnid"]=intval($this->para['ch']); //取参数中的频道ID
        $this->vodid=$webVar["vodid"]=intval($this->para['vf']); //取参数中的VOD文件ID
        if(isset($this->para["tab"])) $this->para["tab"]=intval($this->para["tab"]);    //默认tabid
        if(isset($this->para["ag"])) $this->para["ag"]=intval($this->para["ag"]);   //机构id参数
        //没机构参数取用户所在机构
        if(empty($this->para["ag"])) {
            $paraNoAg=true; //网页参数无机构
            $this->para["ag"]=$this->getUserInfo("agent");
            if(null===$this->para["ag"]) $this->para["ag"]=0;
        } else $paraNoAg=false;
        $webVar["agent"]=$this->para["ag"];
        if(empty($this->chnid)) $this->jumpTo(U("Home/goHome",array("agent"=>$this->para["ag"]))); //程序跳走不会再返回

        //2、读取频道信息
        $this->dbUser=D("user");
        $this->dbChannel=D("channel");
        $this->channel=$this->dbChannel->getInfoExt($this->chnid);
        $chnAttr=$this->channel["ext"];
        //若进入页面没带机构id，当前页面取频道ID，前面可能设了取用户的机构ID，这里覆盖
        if($paraNoAg && $this->channel["agent"]>0 ) $this->para["ag"]=$this->channel["agent"];

        //测试频道是否允许播放
        if(!$this->chnAvailable($webVar)){
            //若不允许，提示后转到首页
            $webVar["href"]=U("Home/goHome",array("agent"=>$this->para["ag"]));
            $this->assign($webVar);
            $this->display("Play/showException");
            return;
        }
        //复制频道有用信息
        $idleInt=intval($chnAttr["player"]["operatorIdleInt"]);
        $webVar["operatorIdleInt"]=(empty($idleInt))? 3600:$idleInt;    //播放终端最长不操作时间(秒)
        $webVar["airTime"]=(empty($chnAttr["livetime"]))?0:$chnAttr["livetime"];    //开播时间 YYYY-MM-DD hh:mm:ss
        $webVar["airDuration"]=(empty($chnAttr["livekeep"]))?0:$chnAttr["livekeep"]*60;    //播出时长(秒)
        $webVar["title"]=htmlspecialchars($this->channel["name"]);
        $webVar["desc"]=htmlspecialchars($this->channel["descript"]);
        $webVar["entrytimes"]=$this->channel["entrytimes"];
        $webVar["logoImg"]="http://".$_SERVER["HTTP_HOST"].$this->dbChannel->getLogoImgUrl($chnAttr, $this->chnid);
        $webVar["hiddenLeftTime"]=(empty($chnAttr["hiddenLeftTime"]))?0:$chnAttr["hiddenLeftTime"]; //隐藏开播倒计时及信号状态
        /*
        //3.1 处理系统公告
        if(!isset($this->para["nc"])){
            $webVar['showNotice']=0;
            $webVar["noticeHtml"]="<H1>公告</H1>";
        }
        //3.2 处理频道封面
        $isShowCover=intval($this->channel["ext"]["showCover"]);
        if(!isset($this->para["nc"]) && !empty($isShowCover)){
            $webVar['showCover']=1;
            $webVar["coverHtml"]=$this->channel["coverhtml"];
        }else{
            $webVar['showCover']=0;
            $webVar["coverHtml"]="空白封面";
        }
        */
        //4、按频道类型决定是否需要登录/注册/付费等
        $chnType=$this->channel["type"];  //频道类型
        if(isset($chnAttr['wxonly']) && 'true' == $chnAttr['wxonly']){
            $wxOnly = true;
        } else {
            $wxOnly = false;    //只限微信登录
        }
        $tollChn=(!empty($this->channel["ext"]["userbill"]) && $this->channel["ext"]["userbill"]["isbill"]=="true")?true:false;  //是否是收费频道
        $haveTicket=false;  //是否已经付费，默认值
        $uid=$this->userId();
        //var_dump($uid,$chnType); die();
        if($chnType=="public" && !$tollChn){
            //公开及非收费频道无需登录，但为了处理和统计方便，专门做了一个匿名登录账号anonymous
            if(empty($uid)){
                //没有用户登录
                //用匿名登录
                $this->author->issue('anonymous','');
                $uid=C('anonymousUserId');
            }
            $webVar["forceLayer"]="hide";   //不显示强制操作层
            //forceLayer(强制操作层)为覆盖在播放界面之上的层，要求用户完成一定的动作后才能解除并正常观看
            //forceLayer，目前考虑的功能有：登录(login)，注册频道会员(register)，付费频道订阅(subscribe)，传播(spread), 不显示(hide)。
            //forceLayer采用iframe
        }else{
            //非公开或收费频道必须登录
            if(empty($uid) || C('anonymousUserId') == $uid) {
                //没有用户登录
                $webVar["forceLayer"]="login";
            }else{
                //已登录
                $webVar["forceLayer"]="hide";
                $dbChnUser=D("channelreluser");
                if("private"==$chnType){
                    //检查会员状态，是否需要注册
                    $rt=$dbChnUser->WhatViewer($this->chnid,$uid);
                    //1:可以收看 0:已报名未通过 -1:未报名
                    if(0==$rt || -1==$rt)    $webVar["forceLayer"]="register";   //已报名未通过或未报名
                }
                if($tollChn && "hide"==$webVar["forceLayer"]){
                    //若是收费频道，并且未要求注册会员，检查是否需要付费
                    $haveTicket=$dbChnUser->isHaveTicket($this->chnid,$uid);    //是否已付费
                    if(!$haveTicket) $webVar["forceLayer"]="subscribe";    //请求付费
                }
            }
        }
        //取用户头像
        $webVar["UserHeadImg"]=$this->dbUser->getHeadImg($uid);
//var_dump($webVar["UserHeadImg"]);
        //5 记录传播，检查是否满足传播要求。传播要求与与会员是与的关系，与付费是或的关系
        $du=intval($this->para["du"]);
        $dbSpread=D("spread");
        //系统账号不考虑传播,排除自身传播
//var_dump($du,$uid);
        if($uid>=100 && $du>=100 && $uid!=$du ) {
            //尝试新建传播记录，若记录已存在，访问次数+1
            $dbSpread->execute("insert into __TABLE__(chnid,tuid,suid,activity) values ($this->chnid,$uid,$du,1) on duplicate key update activity=activity+1");
        }
        if($uid>=100) {
            //读取当前用户的成功传播人数
            $cond = array("chnid" => $this->chnid, "suid" => $uid);
            $timesOfSpread = $dbSpread->where($cond)->count();  //当前用户成功传播人数

            //检查是否符合传播要求
            $spreadTarget = intval($chnAttr["spreadTarget"]);
            if ($spreadTarget > $timesOfSpread && ("private" == $chnType || "protect" == $chnType) && !$haveTicket && $webVar["forceLayer"] == "hide") {
                $webVar["forceLayer"] = "spread";    //请求传播
                $webVar["timesOfSpread"] = $timesOfSpread;
                $webVar["spreadTarget"] = $spreadTarget;
            }
        }
//var_dump( $webVar["forceLayer"],$chnType);die();
        //$webVar["forceLayer"]="register";  //为测试的强制赋值



        //6、按频道配置生成中部导航条数据tabs
        $tabArr=$this->dbChannel->getTabs2($chnAttr);
        $tabs=array();
        foreach ($tabArr['tabs'] as $row){
            $tabs[$row['val']]=$row['text'];
        }
        $webVar["tabs"]=$tabs;
        $activetab=$_REQUEST['tab'];	//从前端传入的默认tab编号，这将覆盖频道配置的默认tab
        if(empty($activetab)) $activetab=(empty($tabArr['activetab']))?'':$tabArr['activetab'];
        $webVar["activetab"]=$activetab;

        //6、播放类型 vod/live
        $webVar['chnid']=empty($this->chnid)?"":$this->chnid;
        if(empty($this->vodid)){
            $this->getLivePara($webVar,$webVar['chnid']);
        }else{
            $this->getVodPara($webVar,$this->vodid);    //填写vod相关的参数
        }
        $webVar['uid']=empty($uid)?"":$uid;
        $webVar['account']=$this->getUserInfo("account");
        $webVar["userName"]=$this->userName();

        contextToken::clearToken(self::PLAY_TOKEN);

        //7、暂时用旧的界面处理登录、注册、付费等
        if($webVar["forceLayer"] != "hide"){
            //生成回调地址
            $acceptUrl=$this->pageUrl();
            $acceptUrl=urlencode($acceptUrl);

            setPara('title',$webVar['title']);  //网页标题
            setPara('coverImg',$webVar['cover']);  //封面图片
            setPara("acceptUrl",$acceptUrl);    //成功时跳转地址
            switch ($webVar["forceLayer"]){
                case 'login':   //登录(login)
                    setPara('errorMsg','本频道需要登录后观看');   //提示信息
                    $this->redirect('Home/login', array('chnId'=>$this->chnid, 'wxonly'=>$wxOnly,  'bozhu'=>0, 'acceptUrl'=>$acceptUrl ));
                    break;
                case 'register':    //注册频道会员(register)
                    setPara('errorMsg','');   //提示信息
//var_dump($this->chnid,$this->vodid,$activetab)             ; //die();
                    $this->redirect('Play/showChnRegister',array('chnid'=>$this->chnid,"vodid"=>$this->vodid, "tab"=>$activetab, "agent"=>$this->para["ag"]));
                    break;
                case 'subscribe':   //付费频道订阅(subscribe)
                    $this->redirect('HDPlayer/chnbill', array('chnId'=>$this->chnid));
                    break;
                case 'spread':
                    $webVar["spreadLoginUrl"]=U('Home/login',array('chnId'=>$this->chnid, 'wxonly'=>$wxOnly,  'bozhu'=>0, 'acceptUrl'=>$acceptUrl ));
                    $this->assign($webVar);
                    $webVar["forceLayerHtml"]=$this->fetch("Play:spread");
                    break;
            }
        }else{
            //用户满足播放权限，授予token，本方法生成的页面凭此token申请有播放权限限制的其它数据（如：播放地址）不必再检查权限
            /*$playToken=uniqid("Play",true);
            $webVar['playToken']=$playToken;
            setPara("playToken",$playToken);*/
            $webVar[self::PLAY_TOKEN]=contextToken::newToken(self::PLAY_TOKEN);

        }

        //8、检查用户并发登录限制，写入在线记录
        try{
            $onlineId=D("online")->checknCreate($uid,"web",$this->chnid,$this->userName());
            $webVar["onlineid"]=$onlineId;
        }catch (Exception $e){
            $webVar["href"]=U("Home/goHome",array("agent"=>$this->para["ag"]));
            $webVar["msg"]=$e->getMessage();
            $webVar["btnTxt"]="去首页看看";
            $this->assign($webVar);
            $this->display("Play/showException");
            return;
        }

        //9、增加点击数
        $inc=intval($chnAttr["viewIncRand"]);
        if($inc>1) $inc=mt_rand(1,$inc);
        $this->dbChannel->where("id=".$this->chnid)->setInc("entrytimes",$inc);

        //其它前端需要的参数
        $webVar["aliveTime"]=(empty(C("aliveTime")))? 10:C("aliveTime");    //最大通讯时间间隔(秒)
        $webVar["homeUrl"]=U("Home/goHome",array("agent"=>$this->para["ag"]));  //跳转到首页的地址
        $webVar["isAdmin"]=$this->isAdmin($uid) ?1:0;   //是否为管理员
        //if($this->chnid==1098) $webVar["source"]="http://v2.av365.cn/live/ou.m3u8";
        //$webVar["cover"]="/t/1.jpg";
        //dump($_POST);
        //var_dump( IsAndroid());
        //var_dump( IsWxBrowser());
$webVar['now1']=time();
        $this->assign($webVar);
        $this->show("TPlay:play");

    }

    /**
     * 取得当前页面的URL地址，用于强制用户登录/注册/交费成功后跳回此处。
     * @param $nc bool 是否显示频道封面，默认不显示
     * @return string 本页面的URL地址
     */
    private function pageUrl($nc=true){
        $acceptUrl=$_SERVER["HTTP_ORIGIN"]."/play.html?ch=".$this->chnid;
        if(!empty($this->vodid)) $acceptUrl .= "&vf=".$this->vodid;
        if(!empty($this->para['tab'])) $acceptUrl .= "&tab=".$this->para['tab'];
        if(!empty($this->para['ag'])) $acceptUrl .= "&ag=".$this->para['ag'];
        if(!empty($this->para['du'])) $acceptUrl .= "&du=".$this->para['du'];
        if($nc) $acceptUrl .="&nc=1";
        return $acceptUrl;
    }

    /**
     * 跳转到指定的URL
     * @param $url 要跳转的URL
     */
    private function jumpTo($url){
        $this->assign("url",$url);
        $this->display("Play/jumpTo");
        exit;
    }

    /**
     * 分析url，返回url信息数组，例：http://192.168.31.104:8003/play.html?rr=00&ch=99
     * 返回：array(6) {
     *  ["scheme"] => string(4) "http"
     *  ["host"] => string(14) "192.168.31.104"
     *  ["port"] => int(8003)
     *  ["path"] => string(10) "/play.html"
     *  ["query"] => string(11) "rr=00&ch=99"
     *  ["params"] => array(2) {
     *  ["rr"] => string(2) "00"
     *  ["ch"] => string(2) "99"   }
     *  }
     * @param string $url
     * @return array
     */
    private function analyseUrl($url){
        $urlArr=parse_url($url);
        if(!empty($urlArr["query"])){
            $urlArr['params']=$this->convertUrlQuery($urlArr["query"]);
        }
        return $urlArr;
    }

    /**
     * 将字符串参数变为数组
     * @param $query
     * @return array
     */
    private function convertUrlQuery($query)    {
        $queryParts = explode('&', $query);
        $params = array();
        foreach ($queryParts as $param) {
            $item = explode('=', $param);
            $params[$item[0]] = $item[1];
        }
        return $params;
    }

    /**
     *
     * 按是否手机决定调用竖屏（默认）还是宽屏显示模板
     * 当对应的模板文件不存在时，尝试调用另一种模板
     * @param string $name
     */
    protected function show($name){
        //dump($_POST);dump($_SERVER);  die();
        if(null==$name) $name=ACTION_NAME;	//默认模板与当前action同名
        //$h=$_POST['height']; $w=$_POST['width'];
        //if(!empty($h) && !empty($w)) $scrType=($h>$w)?'h':'w';        else
            $scrType=IsMobile()?'h':'w';

        $name_org = $name;
        if('w'==$scrType) $name .='_w';		//调用宽屏模板

        if(!file_exists_case(T($name)))	{
            //找不到模板，交换宽、竖屏
            $name = ('w'==$scrType)? $name_org: $name.'_w';
        }
        $this->display($name);
    }

    /**
     * 点播指定资源时前端需要的参数。
     * 此方法初始化播放页面及通过ajax切换播放资源时共用
     * @param array $webVar 参数数组，方法内部直接修改此数组
     * @param int $vodid
     */
    private function getVodPara(&$webVar,$vodid){
        $webVar['vodid']=$vodid;
        $webVar["playType"]="vod";

        $dbRf=D("recordfile");
        $vodfile=$dbRf->field("playkey,path,site")->where("id=".$vodid)->find();
        $vodclass=vodBase::instance($vodfile["site"]);
        $webVar["cover"]=$vodclass->getCoverUrl($vodid,$vodfile["playkey"],$vodfile["path"]);
        $webVar["source"]=$vodclass->getVideoUrl($vodid,$vodfile["playkey"],$vodfile["path"]);
        //$webVar["cover"] = $dbRf->getImgMrl($vodfile['path']);   //海报地址
        //$webVar["source"]=$dbRf->getVodMrl($vodid);
        //$webVar['title']=$vodfile['name'];
        //$webVar["desc"]=htmlspecialchars($vodfile["descript"]);
        $dbRf->incAudience($vodid); //记录观看次数
        //dump($webVar);
    }
    /**
     * 频道直播时前端需要的参数。
     * 此方法初始化播放页面及通过ajax切换播放资源时共用
     * 当没有推流或流被关闭时$webVar["sourec"]=""
     * @param array $webVar 参数数组，方法内部直接修改此数组
     * @param int $chnid
     */
    private function getLivePara(&$webVar,$chnid){
        $this->chnid=$chnid;
        if(empty($this->dbChannel)) $this->dbChannel=D("channel");
        if(empty($this->channel)) $this->channel=$this->dbChannel->getInfoExt($chnid);
        $webVar['vodid']="";
        $webVar["playType"]="live";
        $webVar["title"]=$this->channel["name"];
        $webVar["desc"]=htmlspecialchars($this->channel["descript"]); //频道描述
        $webVar["cover"] = $this->dbChannel->getPosterUrl($this->chnid,$this->channel["ext"]);   //海报地址
        $streamDal = D('stream');
        $w = array('id'=>$this->channel['streamid']);
        $row = $streamDal->where($w)->find();
        if("normal"==$row["status"]){
            if($streamDal->isActive($this->channel['streamid'],$row["platform"])){
                //有推流
                $pf = new platform();
                $pf->load($row['platform']);
                $webVar["source"] = $pf->getHls($row['idstring']);
            }else{
                $webVar["source"]="";
            }
        }else{
            //流被关闭
            $webVar["source"]="";
        }

    }

    /**
     * 显示频道会员注册信息
     * 通过POST传入以下参数：
     *  - chnid
     *  - vodid
     *  - tab   活跃的tabID
     *  - agent     机构ID，与机构定制Home相关
     *  - backUrl   注册/登录成功后跳转的地址
     */
    public function showChnRegister(){
        $uid=$this->userId();
        $webVar=array( 'uid'=>$uid);
        $webVar['chnid']=$this->chnid=$_REQUEST["chnid"];
        $webVar['vodid']=$this->vodid=$_REQUEST["vodid"];
        $webVar["tab"]=$this->para["tab"]=$_REQUEST["tab"];
        $webVar["agent"]=$this->para["agent"]=$_REQUEST["agent"];
        $webVar['backUrl']=$this->pageUrl();

        //取频道海报
        if(empty($this->dbChannel)) $this->dbChannel=D("channel");
        if(empty($this->channel)) $this->channel=$this->dbChannel->getInfoExt($this->chnid);
        $webVar["title"]=$this->channel["name"];
        $webVar["poster"] = $this->dbChannel->getPosterUrl($this->chnid,$this->channel["ext"]);   //海报地址

        $this->assign($webVar);
        $this->display("Play:showChnRegister");
    }

    /**
     * 输出频道装修内容
     * @param int $chnid
     */
    public function showChnInfo($chnid=0){
        $dbChannel=D("channel");
        $rec=$dbChannel->where("id=$chnid")->field('name,attr')->find();
        if(empty($rec)){
            echo "找不到频道信息！";
        }else{
            $attr=(null==$rec['attr'])?array():json_decode($rec['attr'],true);
            $this->assign('title',$rec['name']);
            $this->assign('infojson', (is_array($attr['info']))?json_encode2($attr['info']):$attr['info']);
            $this->display('Play:showChnInfo');
        }
    }

    /**
     * 显示频道可用点播资源列表，
     * POST传入参数
     *  - chnid 频道ID
     *  - vodid 当前请求播放的录像文件ID
     */
    public function vodList(){
        $chnid=intval($_POST["chnid"]);
        $vodid=intval($_POST["vodid"]);
        try{
            if($chnid<1) throw new Exception("缺少频道参数");
            if(!contextToken::verifyToken(self::PLAY_TOKEN,$_POST[self::PLAY_TOKEN])) throw new Exception("非法访问。");

            //获取录像文件记录
            $dbVod = D('recordfile');
            $cond=array('channelid'=>$chnid);
            $data = $dbVod->where($cond)->order('seq, createtime desc')->select();
            if(!is_array($data)) throw new Exception("没找到录像资源");

            //整理图片地址
            $vodclass=array();  //建立对象池，不同的site只建立一个对象处理
            foreach($data as $i => $r)   {
                $site=$r['site'];
                if(empty($vodclass[$site]))  $vodclass[$site]=vodBase::instance($site);
                //$data[$i]['imgpath'] = $dbVod->getImgMrl($r['path']);	//由于每次上传图片都会更换名称，因此没必要增加随机链接。
                $data[$i]['imgpath']=$vodclass[$site]->getCoverUrl($r['id'],$r["playkey"],$r["path"]);
            }
        }catch (Exception $e){
            //没有频道ID、找不到录像列表、其它错误
            echo "<div style='width: 100%; text-align: center; padding-top: 1em; font-size: 1.5em; color:#666;'>".$e->getMessage()."</div>";
            return;
        }
//dump($data);
        //取频道的皮肤模板, 支持播放器皮肤定义 2019-01-16 outao
        $chnDal = new ChannelModel();
        $chnAttr=$chnDal->getAttrArray($chnid);
        $theme=(is_string($chnAttr['theme']))?$chnAttr['theme']:"default";

        $webVar=array("chnid"=>$chnid, "vodid"=>$vodid, "theme"=>$theme, "recList"=>$data);
        $webVar[self::PLAY_TOKEN]=$_POST[self::PLAY_TOKEN];
        $this->assign($webVar);
        $this->display('vodList');
    }

    /**
     * 取直播的播放地址及cover地址
     * POST传递以下参数：
     * {"chnid":params.chnid, "agent":params.agent,"playToken":params.params.playToken}
     */
    public function getLiveSourceJson(){
        //echo "getLiveSourceJson".$_POST["playToken"];
        try{
            if(!contextToken::verifyToken("playToken",$_POST["playToken"])) throw new Exception("非法访问。");
            $chnid=intval($_POST['chnid']);
            if(empty($chnid)) throw new Exception("缺少频道ID");
            $webVar=array();
            $this->getLivePara($webVar,$chnid);
            Oajax::successReturn($webVar);
        }catch (Exception $e){
            Oajax::errorReturn($e->getMessage());
        }


    }

    /**
     * 取点播播放地址及cover地址
     * post传递以下参数：
     *  chnid:  所在频道ID
     *  vodid:  vod记录ID
     * playToken: 上下文令牌
     * 正常返回：{"success":"true","source":"<点播播放地址>","title":"","cover":"","vodid":""}
     * 出错返回：{"success":"false","msg":"<出错信息>"}
     */
    public function getVodSourecJson(){
        try{
            if(!contextToken::verifyToken("playToken",$_POST["playToken"])) throw new Exception("非法访问。");
            $vodid=intval($_POST['vodid']);
            if(empty($vodid)) throw new Exception("缺少VODID");
            $webVar=array();
            $this->getVodPara($webVar,$vodid);
            Oajax::successReturn($webVar);
        }catch (Exception $e){
            Oajax::errorReturn($e->getMessage());
        }

    }


}
?>