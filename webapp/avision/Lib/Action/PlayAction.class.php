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
require_once(APP_PATH.'/Common/platform.class.php');

class PlayAction extends SafeAction{

    //以下类属性由main方法初始赋值
    protected $para=null;   //读web参数形成的参数数组
    protected $chnid=0;     //频道ID
    protected $vodid=0;     //vod文件ID
    protected $dbChannel=null;  //channel数据表对象
    protected $channel=null;    //当前频道记录，attr字段已扩展到ext
    
    /**
     * 播放器主入口
     * 接受参数url，在url中分析要播放的频道及VOD文件，例如：www.av365.cn/play.html?ch=1098&fl=9832
     *  - ch: 频道ID
     *  - vf: VOD记录ID
     *  - nc: 无论什么值，有此参数则不显示频道封面
     *  - tab: 默认的活跃tabid
     * 当不提供ch或找不到ch指出的频道时，显示首页。
     * 仅提供ch时，初始化进入直播状态，若不开放VOD tab则无法切换到VOD状态
     * 提供的vf时，初始化进入VOD状态，若不开放VOD tabs则无法显示VOD列表从而切换其它VOD资源。若不开放live tab无法切换到直播状态。
     *
     *
    */
    public function main(){
        //sleep(1);
        //a=explode("=","nc2");
        //var_export($a); exit();
        $webVar=array();
        $agent=$this->getUserInfo("agent");
        if(null===$agent) $agent=0;

        //1、分析传入参数，若无传入参数或参数不包含ch，跳转到首页
        $url=$_REQUEST['url'];
        if(empty($url)) $this->jumpTo(U("Home/goHome",array("agent"=>$agent)));

        $this->para=$this->analyseUrl($url)["params"];    //传入的参数数组
        $this->chnid=intval($this->para['ch']); //取参数中的频道ID
        $this->vodid=intval($this->para['vf']); //取参数中的VOD文件ID
        if(empty($this->chnid)) $this->jumpTo(U("Home/goHome",array("agent"=>$agent))); //程序跳走不会再返回

        //2、读取频道信息
        $this->dbChannel=D("channel");
        $this->channel=$this->dbChannel->getInfoExt($this->chnid);
        //找不到频道
        if(empty($this->channel)){
            $webVar["msg"]="频道找不到了！";
            $webVar["btnTxt"]="去首页看看";
            $webVar["href"]=U("Home/goHome",array("agent"=>$agent));
            $this->assign($webVar);
            $this->display("Play/showException");
            return;
        }
        //频道关闭
        if('normal'!=$this->channel['status']){
            if('ban'==$this->channel['status']) $webVar["msg"]="频道已由平台关闭！";
            else $webVar["msg"]="频道已关闭！";
            $webVar["btnTxt"]="去首页看看";
            $webVar["href"]=U("Home/goHome",array("agent"=>$agent));
            $this->assign($webVar);
            $this->display("Play/showException");
            return;
        }

        //3、处理频道封面
        $isShowCover=intval($this->channel["ext"]["showCover"]);
        if(!isset($this->para["nc"]) && !empty($isShowCover)){
            $webVar['showCover']=1;
            $webVar["coverHtml"]=$this->channel["coverhtml"];
        }else{
            $webVar['showCover']=0;
            $webVar["coverHtml"]="空白封面";
        }

        //4、按频道类型决定是否需要登录/注册/付费等
        $chnType=$this->channel["type"];  //频道类型
        $tollChn=(!empty($this->channel["ext"]["userbill"]) && $this->channel["ext"]["userbill"]["isbill"]=="true")?true:false;  //是否是收费频道
        $uid=$this->userId();
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
            //forceLayer，目前考虑的功能有：登录(login)，注册频道会员(register)，付费频道订阅(subscribe)，不显示(hide)。
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
                    if(!$dbChnUser->isHaveTicket($this->chnid,$uid)) $webVar["forceLayer"]="subscribe";    //请求付费
                }
            }
        }
        $webVar["forceLayer"]="login";  //为测试的强制赋值


        //5、按频道配置生成中部导航条数据tabs
        $chnAttr=$this->channel["ext"];
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
            $webVar['vodid']="";
            $webVar["playType"]="live";
            $webVar["cover"] = $this->dbChannel->getPosterUrl($this->chnid,$chnAttr);   //海报地址
            $streamDal = D('stream');
            $w = array('id'=>$this->channel['streamid']);
            $row = $streamDal->where($w)->find();
            $pf = new platform();
            $pf->load($row['platform']);
            $webVar["source"] = $pf->getHls($row['idstring']);
        }else{
            $dbRf=D("recordfile");
            $vodfile=$dbRf->where("id=".$this->vodid)->find();
            $webVar['vodid']=$this->vodid;
            $webVar["playType"]="vod";
            $webVar["cover"] = $dbRf->getImgMrl($vodfile['path']);   //海报地址
            $webVar["source"]=$dbRf->getVodMrl($this->vodid);
        }
        $webVar['uid']=empty($uid)?"":$uid;


        //$webVar["source"]="http://www.av365.cn/ts/dfhc.mp4";
        //$webVar["cover"]="/t/1.jpg";
        //dump($_POST);
        //var_dump( IsAndroid());
        //var_dump( IsWxBrowser());
        //
        $this->assign($webVar);
        $this->show("Play/play");

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
        if(null==$name) $name=ACTION_NAME;	//默认模板与当前action同名
        //$scrType=getPara('scrType');
        $scrType=IsMobile()?'h':'w';

        $name_org = $name;
        if('w'==$scrType) $name .='_w';		//调用宽屏模板

        if(!file_exists_case(T($name)))	{
            //找不到模板，交换宽、竖屏
            $name = ('w'==$scrType)? $name_org: $name.'_w';
        }
        $this->display($name);
    }

    public function blkini(){
        echo "pppwpwpwpw\n";
        echo <<<EOF
<script>
(function() {
  //alert("tttt");
  console.log($("#pp11").html());
})();
    
</script>
<div id="pp11">pp1122</div>
EOF;

    }
}