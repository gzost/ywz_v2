/**
 * 统一下单并支付接口，注意此接口不会检查业务逻辑的合理性及页面的安全性。
 * 同一页面只能有一个实例。同一实例通过setData可设置不同的订单参数，
 * 不管订单参数是否相同，每调用次pay方法都认为是新的支付订单。
 *
 * 依赖：jquery.js,    jquery.query.js
 * 版本：2022.07.22
 * 使用：
 * 页面引入 OU_unifiedorder.css默认样式
 * @code
 *  var order=new OU_unifiedorder();
 *  var data={
 *          summary:"订单摘要",
 *          amt:"订单总金额(分)",
 *          productid:"产品id(H5支付必须)",
 *          userid:"此订单归属的用户ID",    //订单的支出及购买到的商品都归属此用户，一般是当前登录用户
 *          contexid:"sessionid",   //记录网页上下文，保证是可靠的连续会话
 *          opendid:"微信支付时支付用户的微信关联标识"  //JSAPI支付时使用，若不提供会发起用户有感知的查询
 *                                      //也可以通过URL变量提供，此参数覆盖URL变量
 *          app:"/home.php"  //thinkphp项目入口
 *
 *  order.setData(data);
 *  order.pay(function (payResult) {
 *      //支付结果回调，支付成功：payResult=
 *      //{ result:"cancel|ok", //cancel-失败，ok-成功
 *      //  tradeno:"商户订单号"
 *      // }
 *       console.log("payResult=",payResult);
 *   });
 *
 */
function OU_unifiedorder(){

    const OPENID_URL="/WxpayHelper/getOpenidAttrAjax";   //取用户属性中的openid
    const GETOPENID_FROM_WX="/WxpayHelper/getOpenid";    //通过微信SDK获取openid
    const PAY_URL="/WxpayHelper/pay";
    const NATIVEPAY_URL="/WxpayHelper/nativePay";
    const POPUP_WIN_ID="#jqWindow";

    var _this=this;
    var payArg={};    //存储提交到统一下单的数据
    var callback;
    var app="/index.php";


    //建立弹出窗口的DOM对象
    var popUpWindow=$(
        "<div id='jqWindow' class='easyui-window'><div id='innerBlk'>" +
        "<div class='title'>生成订单</div>" +
        "</div></div>"
    );
    popUpWindow.attr('data-options',"iconCls:'icon-save',modal:true,border:false,noheader:true,closed:true");
    popUpWindow.css({"width":"99%","max-width":"480px", "height":"400px"});
    $("body").append(popUpWindow);
    $.parser.parse(popUpWindow.parent());   //渲染窗口

    //判断是否为微信浏览器
    var isWx=function () {
        let ua=navigator.userAgent.toLowerCase();
        return (ua.match(/MicroMessenger/i) == "micromessenger");
    }

    var getArg=function (name) {
        return payArg[name];
    }

    var urlencode=function(str){
        str=(str+'').toString();
        return encodeURIComponent(str).replace(/!/g,'%21').replace(/'/g,'%27').replace(/\(/g,'%28').
        replace(/\)/g,'%29').replace(/\*/g,'%2A').replace(/%20/g,'+');
    }
    //显示订单确认控件并调用jsapi支付
    var showpay=function (url) {
        $(POPUP_WIN_ID).find("#innerBlk").load(url,payArg);
        $(POPUP_WIN_ID).window({closed:false});
        $.parser.parse("#innerBlk");   //渲染窗口
    }
    //将数据复制到类属性中
    this.setData=function(d){
        $.extend(payArg,d);
        if('string' == typeof payArg['app']) app=payArg['app'];
    }
    /**
     *  确认订单并支付
     *  payCallback是回调函数，支付完成后返回支付结果：
     *  { result:"cancel|ok", //cancel-失败，ok-成功
     *    tradeno:"商户订单号"
     *    }
     */
    this.pay=function (payCallback) {
        if('function' == typeof payCallback) callback=payCallback;
        $(POPUP_WIN_ID).find("#innerBlk").html('');

        if(isWx()){
            /**
             * 若是微信浏览器，先获取OpenID
             * OPENID_URL：若用户已登录且有openID，ajax方式输出Openid
             */
            //1、检查传入参数是否有openid,有则使用
            let openid=payArg.openid;
            if('string' == typeof openid  && openid.length> 20 ){
                showpay(app+PAY_URL);
            }else {
                //2、URL带openid参数直接使用
                openid=$.query.get("openid");
                if('string' == typeof openid  && openid.length> 20) {
                    payArg.openid = openid;
                    showpay(app + PAY_URL);
                }else{
                    //都没有openid,主动发起查询
                    $.post(app+OPENID_URL,function (rtStr) {
                        console.log("openid=",rtStr,typeof rtStr);
                        if('string' == typeof rtStr  && rtStr.length> 20) {
                            //已获得Openid
                            payArg.openid=rtStr;
                            showpay(app+PAY_URL);
                        }else{
                            //通过用户属性未获得Openid，通过微信SDK获得
                            console.log("have no openid attr , to get from WX");
                            $.messager.confirm('确认','支付订单需要微信授权，继续吗？',function (r) {
                                if(r){
                                    let bzUrl=urlencode(window.location.href);
                                    console.log("bzUrl="+bzUrl);
                                    let url=app+GETOPENID_FROM_WX+$.query.set('bzUrl',bzUrl).set('tips','yes');
                                    console.log("jump to:"+url);
                                    window.location.href=url;
                                }
                            });
                        }
                    },'HTML');
                }
            }
        }else{
            //非微信浏览器
            showpay(app+NATIVEPAY_URL);
        }
    }

    /**
     * 支付控件在完成支付后会向window发出postpay消息，同时带有结果参数：{result:"ok|cancel",tradeno:"商户订单id"}
     * result: 成功-ok，失败-cancel
     * 支付失败不一定是没支付，可能是支付信息反馈不及时。可稍后查询订单
     */
    $(window).off("postpay");   //避免多次绑定消息响应
    $(window).on("postpay",function (event,result) {
        console.log("postpay=",result);
        $(POPUP_WIN_ID).window({closed:true});
        console.log(typeof callback);
        if('function' == typeof callback) callback(result);
    });

}   //OU_unifiedorder