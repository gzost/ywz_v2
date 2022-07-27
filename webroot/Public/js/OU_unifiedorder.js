/**
 * 统一下单并支付接口，注意此接口不会检查业务逻辑的合理性及页面的安全性。
 * 同一页面只能有一个实例。
 * 依赖：jquery.js,    jquery.query.js
 * 版本：2022.07.22
 * 使用：
 * 页面引入 OU_unifiedorder.css默认样式
 * @code
 *  var order=new OU_unifiedorder();
 *  var data=[{name:"summary",value:"订单摘要"},{name:"amt",value:"订单总金额(分)"},
 *          {name:"productid",value:"产品id(H5支付必须)"},
 *          {name:"userid",value:"当前登录用户ID"},{name:"contextid",value:"sessionid"}]
 *  order.setData(data);
 *  order.pay(function (payResult) {
         *      //支付结果回调，支付成功：payResult="ok"，支付失败="cancel"
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
    var payArg=[];    //存储提交到统一下单的数据，serializeArray的输出格式:[{name:"变量名",value:"变量值"},{...}]
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
        for(var key in payArg){
            if('string'==typeof(payArg[key].name) && payArg[key].name==name && payArg[key].value!=""){
                return payArg[key].value;
            }
        }
        return null;
    }
    //将serializeArray的输出格式转成URL参数串
    var arrayToParam=function () {
        var param='';
        $.each(payArg,function(i,arg){
            param += arg.name+"="+arg.value+"&";
        });
        return param;
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
        payArg=Object.assign([],d);
        let tmp=getArg("app");
console.log("tmp=",tmp);
        if('string'==typeof tmp) app=tmp;
    }
    /**
     *  确认订单并支付
     *  payCallback是回调函数，支付完成后返回支付结果：cancel-失败，ok-成功
     */
    this.pay=function (payCallback) {
        if('function' == typeof payCallback) callback=payCallback;
        $(POPUP_WIN_ID).find("#innerBlk").html('');

        if(isWx()){
            /**
             * 若是微信浏览器，先获取OpenID
             * OPENID_URL：若用户已登录且有openID，ajax方式输出Openid
             */
                //调用URL带openid参数直接使用
            let openid=$.query.get("openid");
            if('string' == typeof openid  && openid.length> 20) {
                payArg.push({name:"openid",value:openid});
                showpay(app+PAY_URL);
            }else {
                $.post(app+OPENID_URL,function (rtStr) {
                    console.log("openid=",rtStr,typeof rtStr);
                    if('string' == typeof rtStr  && rtStr.length> 20) {
                        //已获得Openid
                        payArg.push({name:"openid",value:rtStr});
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
        }else{
            //非微信浏览器
            showpay(app+NATIVEPAY_URL);
        }
    }

    /**
     * 支付控件在完成支付后会向window发出postpay消息，同时带有结果参数：成功-ok，失败-cancel
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