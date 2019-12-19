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

class PlayAction extends SafeAction{

    /**
     * 播放器主入口
     * 接受参数url，在url中分析要播放的频道及VOD文件，例如：www.av365.cn/play.html?ch=1098&fl=9832
     *  - ch: 频道ID
     *  - fl: VOD记录ID
     *  - nc: 无论什么值，有此参数则不显示频道封面
     * 当不提供ch或找不到ch指出的频道时，显示首页。
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

        $para=$this->analyseUrl($url)["params"];    //传入的参数数组
        $chnid=intval($para['ch']); //取参数中的频道ID
        if(empty($chnid)) $this->jumpTo(U("Home/goHome",array("agent"=>$agent)));

        //2、读取频道信息
        $dbChannel=D("channel");
        $channel=$dbChannel->getInfoExt($chnid);
        if(empty($channel)){
            $webVar["msg"]="频道找不到了！";
            $webVar["btnTxt"]="去首页看看";
            $webVar["href"]=U("Home/goHome",array("agent"=>$agent));
            $this->assign($webVar);
            $this->display("Play/showException");
            return;
        }

        //3、处理频道封面
        $isShowCover=intval($channel["ext"]["showCover"]);
        if(!isset($para["nc"]) && !empty($isShowCover)){
            $webVar['showCover']=1;
            $webVar["coverHtml"]=$channel["coverhtml"];
        }else{
            $webVar['showCover']=0;
            $webVar["coverHtml"]="空白封面";
        }

        //4、按频道类型决定是否需要登录/注册/付费等
        $chnType=$channel["type"];  //频道类型
        $tollChn=(!empty($channel["ext"]["userbill"]) && $channel["ext"]["userbill"]["isbill"]=="true")?true:false;  //是否是收费频道
        $uid=$this->userId();
        if($chnType=="public" && !$tollChn){
            //公开及非收费频道无需登录，但为了处理和统计方便，专门做了一个匿名登录账号anonymous
            if(empty($uid)){
                //没有用户登录
                //用匿名登录
                $this->author->issue('anonymous','');
            }
            $webVar["forceLayer"]="hide";   //不显示强制操作层
            //forceLayer(强制操作层)为覆盖在播放界面之上的层，要求用户完成一定的动作后才能解除并正常观看
            //forceLayer，目前考虑的功能有：登录(login)，注册频道会员(register)，付费频道订阅(subscribe)。
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
                }
               if($tollChn && "hide"==$webVar["forceLayer"]){
                    //若是收费频道，并且未要求注册会员，检查是否需要付费
               }
            }
        }
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
    public function show($name){
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
}