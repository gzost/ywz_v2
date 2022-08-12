<?php
/**
 * Created by PhpStorm.
 * User: outao
 * Date: 2022/6/14
 * Time: 11:50
 */

define('WXPAYSDK_PATH',APP_PUBLIC.'wxpaysdk3.0.10');
require_once WXPAYSDK_PATH."/lib/WxPay.Api.php";
require_once WXPAYSDK_PATH.'/lib/WxPay.Notify.php';
require_once WXPAYSDK_PATH."/helper/WxPay.JsApiPay.php";
require_once WXPAYSDK_PATH."/helper/WxPay.Config.php";
require_once WXPAYSDK_PATH."/helper/phpqrcode.php";
//require_once WXPAYSDK_PATH.'/helper/log.php';

require_once APP_PATH.'../public/Authorize.Class.php';
require_once APP_PATH.'../public/Ou.Function.php';
class WxpayHelperAction extends Action
{
    public function __construct(){
        parent::__construct();
        session_start();
        C('LOGFILE_LEVEL', LogLevel::SQL);
        C('LOG_FILE','wxpaydebug.log');
    }

    public function t(){
        $no=date("YmdHis").'Y'.base_convert(mt_rand(1000000000,2099999999).substr(microtime(),2,6),10,36);
        $min=base_convert('1000000000',10,36);
        echo $min.' len='.strlen($min).'<br>';
        $max=base_convert('2099999999',10,36);
        echo $max.' len='.strlen($max).'<br>';
        echo $no;
        echo "<p>";
        echo strlen($no);
        return;
        $webvar=array("msg"=>"成功授权", "bzUrl"=>"http://ou.av365.cn:8003/index.php/WxpayHelper/buy");
        $this->assign($webvar);
        $this->display("getOpenidTips");
    }

    /**
     * 用于测试，从购买按钮开始的支付过程，仅用于同一服务器的支付。
     */
    public function buy(){

        $authorize=new  authorize();
        $webvar=array("summary"=>"1000分钟流量包","amt"=>1, "userid"=>$authorize->getUserInfo('userId'),
            "contextid"=>session_id(), "productid"=>123456);
        $this->assign($webvar);
        $this->display("buy");
    }


    /**
     * 输出用户属性中的openid，没设置输出''
     */
    public function getOpenidAttrAjax(){
        $authorize=new  authorize();
        $openid = ($authorize->isLogin()) ? $authorize->getUserInfo('wxopenid'):'';
        //$openid='o3NkBwJXZsiaq4ig4ufadW-umL9I';
        //$openid='';
        echo $openid;
    }

    /**
     * 通过微信SDK接口，获取当前用户的Openid
     * 调用时参数：
     * bzUrl: 成功获取openid后要跳回的url，自动附加openid参数，若不设置则不跳转仅return openid
     * save: 默认'yes',把获得的openid填写到用户表以及当前的用户属性中, 'no'-不进行操作
     * tips: 默认'yes',跳转到业务页面前进行提示，'no'-直接跳转
     * code: 微信回调时附加的参数，作为获取openid的凭证
     */
    public function getOpenid(){
        $tools = new JsApiPay();
        //当未携带code参数时，函数内部生成向WX申请code调用并终止，callbackURL填写为当前的URL
        //因此，获得URL时，实际上是WX的callback
        $openId = $tools->GetOpenid();
        //$openId='o3NkBwJXZsiaq4ig4ufadW-umL9I';
        $argTpl=array("bzUrl"=>"", "save"=>"yes", "tips"=>"yes");
        $webvar=getRec($argTpl,false);
        if(''==$webvar['bzUrl']) return $openId;
        $webvar['bzUrl']=urldecode($webvar['bzUrl']);
        $webvar['bzUrl'] .=(strpos($webvar['bzUrl'],'?')>0)?'&':'?';
        $webvar['bzUrl'] .="openid=".$openId;   //回调URL附带openid参数
        try{
            if(strlen($openId) <20) throw new Exception("获取微信授权信息失败。");
            if('yes'==$webvar["save"]){
                //用户与微信绑定
                $authorize=new  authorize();
                $userid=$authorize->getUserInfo('userId');
                if($userid<=20) throw new Exception("未登录或系统用户无法绑定微信。");
                $rt=D('user')->where("id=".$userid)->save(array("wxopenid"=>$openId));
                if(false===$rt) throw new Exception("无法更新用户数据");
                $authorize->setUserInfo('wxopenid',$openId);
            }
            if('yes'==$webvar['tips']) throw new Exception("微信授权成功。");
            redirect($webvar['bzUrl']);

        }catch (Exception $e){
            $webvar['msg']=$e->getMessage();
            $this->assign($webvar);
            $this->display('getOpenidTips');
        }

    }

    /**
     * 统一下单，传入订单基本信息及支付（交易）类型
     * @param array $orderArg    订单基本信息
     *  -trade_type:    必须，交易类型JSAPI/NATIVE
     * @return array 微信统一下单输出的数据
     * @throws Exception 统一下单失败抛出错误
     */
    private function unifiedOrder($orderArg){
        $trade_no=date("YmdHis").'Y'.base_convert(mt_rand(1000000000,2099999999).substr(microtime(),2,6),10,36);
        $now=time();
        if(null===$orderArg['productid']) $orderArg['productid']='';
        if(null===$orderArg['openid']) $orderArg['openid']='';
        if(null==$orderArg['userid']) throw new Exception('缺少用户ID');
        if('JSAPI'!=$orderArg['tradetype'] && 'NATIVE'!=$orderArg['tradetype']) throw new Exception('不支持的支付模式');

        $input = new WxPayUnifiedOrder();
        $input->SetBody($orderArg["summary"]);
        $input->SetAttach("ywz");
        $input->SetOut_trade_no($trade_no);
        $input->SetTotal_fee($orderArg['amt']);
        $input->SetTime_start(date("YmdHis",$now));
        $input->SetTime_expire(date("YmdHis", $now + 1800));    //订单有效时间半小时
        $notifyUrl=sprintf("%s://%s/home.php/WxpayHelper/payNotify",$_SERVER['REQUEST_SCHEME'],$_SERVER['HTTP_HOST']);
        $input->SetNotify_url($notifyUrl);    //("http://paysdk.weixin.qq.com/notify.php");
        $input->SetTrade_type($orderArg['tradetype']);
        if('JSAPI'==$orderArg['tradetype']){
            $input->SetOpenid($orderArg['openid']);
        }else{
            $input->SetProduct_id($orderArg['productid']);
        }

        $config = new WxPayConfig();
        $order = WxPayApi::unifiedOrder($config, $input);

        if('SUCCESS'==$order['return_code'] && 'SUCCESS'==$order['result_code']){
            //下单成功
            logfile("UnifiedOrder return: ".json_encode2($order),LogLevel::NOTICE);
            $db=D("salesorder");
            $orderRec=array(
                'tradeno'=>$trade_no,
                'tradetime'=>date('Y-m-d H:i:s',$now),
                'title'=>$orderArg["summary"],
                'amt'=>$orderArg["amt"],
                'openid'=>$orderArg["openid"],
                'prepayid'=>$order['prepay_id'],    //有效时间2小时
                'productid'=>$orderArg["productid"],
                'userid'=>$orderArg['userid'],
                'tradetype'=>$orderArg["tradetype"]
            );
            $rt=$db->add($orderRec);
            if(false==$rt){
                logfile("Create Order Err:".$db->getLastSql(),LogLevel::EMERG);
                throw new Exception('建立预付订单失败');
            }
        }else{
            //下单失败
            logfile("UnifiedOrder return: ".json_encode2($order),LogLevel::CRIT);
            throw new Exception('统一下单失败。');
        }
        return $order;
    }
    /**
     * 判断当前浏览器是否是微信
     */
    public function isWx(){
        if( strpost($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) return true;
        else return false;
    }
    /**
     * 统一下单，并根据前端是否使用微信分别调用jsapi/native支付，必须通过post或get提供以下参数：
     * summary string: 订单(商品)的摘要
     * amt int: 订单总金额。单位：分
     * userid: 订单关联的用户ID
     * contextid: session_id, 保证是本网站内的连贯操作。
     */
    public function pay() {
        $templet=array('summary'=>null, 'amt'=>null, 'userid'=>null, 'contextid'=>null, 'openid'=>null,'productid'=>0);
        $orderRec=getRec($templet,true);  //订单记录
        //
        //echo "pay:"; return;
//dump($orderRec,session_id());
        try {
            //①检查是否提供了必要的参数
            if($orderRec['contextid']!=session_id() || null==$orderRec['summary'] ||
                null==$orderRec['amt'] || null==$orderRec['userid'] || null==$orderRec['openid'])
                throw new Exception("缺少必要的参数！");
            $webvar=$orderRec;
            $webvar['amt']=$orderRec['amt']/100.0;


            //②、统一下单
            $orderRec['tradetype']='JSAPI';  //支付模式必须填写JSAPI/NATIVE
            $order=$this->unifiedOrder($orderRec);
//dump($order);
            $tools = new JsApiPay();
            $jsApiParameters = $tools->GetJsApiParameters($order);
logfile("NotifyUrl:".$notifyUrl,LogLevel::DEBUG);
logfile("支付参数：".$jsApiParameters,LogLevel::DEBUG);
//dump($jsApiParameters);
            //获取共享收货地址js函数参数
            $editAddress = $tools->GetEditAddressParameters();
//dump($editAddress);
            $webvar["jsApiParameters"]=$jsApiParameters;
            $webvar["editAddress"]=$editAddress;
            $this->assign($webvar);
            $this->display("pay");
            //③、在支持成功回调通知中处理成功之后的事宜，见 notify.php
            /**
             * 注意：
             * 1、当你的回调地址不可访问的时候，回调通知会失败，可以通过查询订单来确认支付是否成功
             * 2、jsapi支付时需要填入用户openid，WxPay.JsApiPay.php中有获取openid流程 （文档可以参考微信公众平台“网页授权接口”，
             * 参考http://mp.weixin.qq.com/wiki/17/c0f37d5704f0b64713d5d2c37b468d75.html）
             */
        } catch (Exception $e) {
            logfile($e->getMessage(), LogLevel::CRIT);
            $webvar['msg'] = $e->getMessage();
            $this->assign($webvar);
            $this->display("payErr");
        }
    }

    /**
     * H5支付
     *
     */
    public function nativePay(){
        $templet=array('summary'=>null, 'amt'=>null, 'userid'=>null, 'contextid'=>null, 'openid'=>null,'productid'=>0);
        $orderRec=getRec($templet,true);  //订单记录

        //echo "pay:"; return;
//dump($orderRec,session_id());
        try {
            //1、检查是否提供了必要的参数
            if($orderRec['contextid']!=session_id() || null==$orderRec['summary'] ||
                null==$orderRec['amt'] || null==$orderRec['userid'] || 0==$orderRec['productid'])
                throw new Exception("缺少必要的参数！");
            $webvar=$orderRec;
            $webvar['amt']=$orderRec['amt']/100.0;

            $orderRec['tradetype']='NATIVE';  //支付模式必须填写JSAPI/NATIVE
            $result=$this->unifiedOrder($orderRec);
//dump($result);
            $webvar['code_url']=urlencode($result['code_url']);    //收款二维码
            $webvar['prepayid']=$result['prepay_id'];
            $this->assign($webvar);
            $this->display("nativePay");
        } catch(Exception $e) {
            logfile(json_encode($e),LogLevel::CRIT);
            $webvar['msg'] = $e->getMessage();
            $this->assign($webvar);
            $this->display("payErr");
        }
    }

    /**
     * 微信支付回调函数。由微信服务器发起调用
     */
    public function payNotify(){
        logfile('begin notify:', LogLevel::DEBUG);
        $config = new WxPayConfig();
        $notify = new PayNotifyCallBack();
        $notify->Handle($config, false);
    }

    //根据GET传递的data字串，生成二维码
    public function qrcode(){
        $url = urldecode($_GET["data"]);
        if(substr($url, 0, 6) == "weixin"){
            QRcode::png($url);
        }else{
            header('HTTP/1.1 404 Not Found');
        }
    }

    /**
     * 处理前端长查询请求。根据prepayid，查询并返回支付结果，没有结果时，在60秒内循环查询。
     * @param $prepayid
     */
    public function checkPayJson($prepayid){
        if(null==$prepayid) Oajax::errorReturn('缺少参数。');
        $expireTime=time()+60;    //程序最长查询时间60s
//OUwrite($expireTime.'<br>');
        $db=D('salesorder');
        $cond=array('prepayid'=>$prepayid);
        do{
            $payresult=$db->where($cond)->getField('payresult');
//var_dump($payresult,$db->getLastSql());
            if('SUCCESS'==$payresult) Oajax::successReturn();   //订单已经成功支付，返回
            if(null===$payresult) Oajax::errorReturn('无效的预付订单。');   //错误返回
            sleep(2);
//OUwrite(time().'<br>');
        }while(time()<$expireTime);
        if(''==$payresult) Oajax::errorReturn('等待支付订单。');
        logfile("订单支付汇报错误：".$payresult,LogLevel::ERR);
        Oajax::errorReturn('订单支付状态出错，请稍后再查询。');
    }
}

/**
 * 微信支付回调处理类，为适应SDK架构
 */
class PayNotifyCallBack extends WxPayNotify
{
    //查询订单
    public function Queryorder($transaction_id)
    {
        $input = new WxPayOrderQuery();
        $input->SetTransaction_id($transaction_id);

        $config = new WxPayConfig();
        $result = WxPayApi::orderQuery($config, $input);
        //Log::DEBUG("query:" . json_encode($result));
        logfile("Queryorder=".json_encode($result),LogLevel::DEBUG);
        if(array_key_exists("return_code", $result)
            && array_key_exists("result_code", $result)
            && $result["return_code"] == "SUCCESS"
            && $result["result_code"] == "SUCCESS")
        {
            return true;
        }
        return false;
    }

    /**
     *
     * 回包前的回调方法
     * 业务可以继承该方法，打印日志方便定位
     * @param string $xmlData 返回的xml参数
     *
     **/
    public function LogAfterProcess($xmlData) {
        logfile("返回给微信：".$xmlData, LogLevel::DEBUG);
        return;
    }

    //重写回调处理函数
    /**
     * @param WxPayNotifyResults $data 回调解释出的参数
     * @param WxPayConfigInterface $config
     * @param string $msg 如果回调处理失败，可以将错误信息输出到该方法
     * @return true回调出来完成不需要继续回调，false回调处理未完成需要继续回调
     */
    public function NotifyProcess($objData, $config, &$msg)
    {
        $data = $objData->GetValues();
        logfile("Notify data:".json_encode2($data), LogLevel::DEBUG);
        try{
            //1、进行参数校验
            if(!array_key_exists("return_code", $data)
                ||(array_key_exists("return_code", $data) && $data['return_code'] != "SUCCESS")) {
                //TODO失败,不是支付成功的通知
                //如果有需要可以做失败时候的一些清理处理，并且做一些监控
                throw new Exception("支付通讯错误：".$data['return_msg']);
            }
            //检查业务结果
            if(!array_key_exists("result_code", $data)
                ||(array_key_exists("result_code", $data) && $data['result_code'] != "SUCCESS")) {
                throw new Exception("支付失败：".$data['err_code'].$data['err_code_des']);
            }

            if(!array_key_exists("transaction_id", $data))  throw new Exception('缺少预付订单号。');
            if(!array_key_exists("openid", $data))  throw new Exception('缺少openid。');
            if(!array_key_exists("total_fee", $data))  throw new Exception('缺少订单金额。');
            if(!array_key_exists("out_trade_no", $data))  throw new Exception('缺少商户订单号。');

            //TODO 2、进行签名验证
            $checkResult = $objData->CheckSign($config);
            if($checkResult == false){
                throw new Exception('签名验证失败。');
            }

            //查询订单，判断订单真实性
            if(!$this->Queryorder($data["transaction_id"])){
                throw new Exception('订单查询失败。');
            }

            logfile('支付回调结果成功.', LogLevel::DEBUG);
            $payresult="SUCCESS";
            $returnCode=true;

        }catch (Exception $e){
            logfile('支付回调结果错误：'.$e->getMessage(), LogLevel::ERR);
            $payresult=$e->getMessage();
            $returnCode= false;
        }


        //TODO 3、处理业务逻辑
        //收到回调，无论是否成功都做数据库记录。
        $db=D('salesorder');
        $orderRec=array(
            'openid'=>$data['openid'],
            'transactionid'=>$data['transaction_id'],
            'paytime'=>date('Y-m-d H:i:s'),
            'payresult'=>$payresult
        );
        $cond=array('tradeno'=>$data['out_trade_no'],'amt'=>$data['total_fee']);
        $rt=$db->where($cond)->save($orderRec);
        if($rt<1){
            logfile('收到支付结果后未能更新数据库：'.$db->getLastSql(),LogLevel::EMERG);
        }else{
            logfile("成功更新数据库。");
        }

        return $returnCode;

    }
}