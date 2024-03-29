
/**
 * 播放页面主要js集合
 */

//////////与后端通讯类//////////
function Ou_Communicate(options) {
    var defaults={
        //各种默认参数，各种属性
        backend:"",         //后端URL
        tokenName:"",       //通讯令牌名称
        tokenValue:"",
        lostCount:5         //5+1次联络服务器出错(响应超时或无法连接)发出ServerLost消息
    }
    var params = $.extend(defaults,options);
    var lostCount=params.lostCount; //服务器丢失倒计数
    /**
     *  向服务端发送数据
     *  @param sendData 要发送的数据对象
     *  成功发送并收到服务器回应后，发送RecvData消息
     */
    this.send=function(sendData,callback){
        //alert("ggssss");
        var sendTime=new Date().getTime();  //毫秒为单位的时间戳，向后端发送当前时间戳，用于校准服务器与终端间的时间，同时也可计算通讯延迟。
        var commPkg={tokenName:params.tokenName,tokenValue:params.tokenValue,sendTime:sendTime,playload:sendData} //数据包
        console.log("Communicate Send:",commPkg);
        $.ajax({
            url: params.backend,
            type: 'post',
            data: commPkg,
            cache:false,
            async:true,
            dataType: 'json',
            success:function (recvData) {
                console.log("Communicate recv:",recvData);
                if(recvData["success"]=="false"){
                    console.log(recvData["msg"]); //在控制台显示错误
                    if(lostCount>0) lostCount--;
                    else $(window).trigger("ServerLost");   //发送ServerLost消息
                }else{
                    lostCount=params.lostCount;
                    console.log("Send trigger RecvData.......");
                    $(window).trigger("RecvData",recvData);
                    if(typeof callback === "function") callback(recvData);
                }
            },
            error:function () {
                if(lostCount>0) lostCount--;
                else $(window).trigger("ServerLost");   //发送ServerLost消息
                console.log("Server lost :"+lostCount);
                if(typeof callback === "function") callback(null);
            },
            complete:function () {
                console.log("Communicate delay(ms)="+(new Date().getTime()-sendTime),typeof callback);
            }
        });
    }
}
function Ou_playPage(params) {
    //本HTML相关的参数，如DOM。在JS内部不直接使用HTML相关信息，降低耦合度
    var local={
        blkForceLayer:"blkForceLayer",
        blkAirTime:".layerVideoTop2 .blk_airTime",
        blkLeftTime:".layerVideoTop2 .blk_leftTime",
        layerVideoTop1:".layerVideoTop1",
        layerVideoTop2:".layerVideoTop2"
    };
    //一些状态控制变量集中管理
    var status={
        tabScrolling:false, //多功能窗口在程序操作滚动中，用于暂停手动滚动事件的响应
        playerReady:false   //播放器准备好
    }
    var _this=this;

    var appPara={}; //应用参数，每次向服务端发送数据时，自动附加。chat参数在chat属性内

    ////////在线记录对象/////////
    var Ou_OnlineTable=function () {
        /**
         * 在线记录表，这是本对象关键的数据结构，格式为：
         * {"<前端在线记录ID>":{
             *      BEid:<后端在线记录ID，在前端建立了记录但后端未确认前保持为0，后端同步了记录后填入后端在线记录ID>,
             *      starttime:<记录开始有效时间戳，0说明此记录尚未生效>,
             *      endtime:<记录设置为无效的时间戳>,
             *      objtype:<后端关联在线类型[web|vod|live]每个活动页面前端至少包含一条且只有一条web类型的记录，其它根据页面布局而定>,
             *      refid:<后端关联对象ID，目前vod类型关联recordfileID,其它关联channelID>,
             *      FEobj:<关联的前端对象，vod|live时关联对应播放窗口的播放器对象>
             *      },
             *  "<前端在线记录ID>":{...}...}
         *  播放状态判断
         *  starttime   endtime
         *      0           0       未播放
         *      n           0       播放中
         *      n           n       播放已结束
         *      0           n       未播放
         */
        var onlineTable={}; //在线记录表当objtype="live"|"vod"时，player填写对应的播放对象

        /**
         * 建立新的在线记录
         */
        this.createOnline=function(id,BEid,starttime,endtime,objtype,refid,FEobj){
            onlineTable[id]={ BEid:BEid, starttime:starttime, endtime:endtime, objtype:objtype, refid:refid, FEobj:FEobj}  //填写在线记录
            console.log(onlineTable);
        }
        this.getOnlineTable=function () {
            return onlineTable;
        }
        this.setOnline=function (id,objtype,refid,FEobj) {
            var now=parseInt((new Date()).getTime()/1000);
            console.log("setting online..");
            //相同资源暂停20秒内重新播放，连续计算播放时间
            if(onlineTable[id]["starttime"]>0 && (now-onlineTable[id]["endtime"])<20 && onlineTable[id]["objtype"]==objtype && onlineTable[id]["refid"]==refid){
                onlineTable[id]["endtime"]=0;
            }else{
                onlineTable[id]["BEid"]=0;
                onlineTable[id]["starttime"]=now;
                onlineTable[id]["endtime"]=0;
                onlineTable[id]["objtype"]=objtype;
                onlineTable[id]["refid"]=refid;
                onlineTable[id]["FEobj"]=FEobj;
            }
            console.log(onlineTable);
        }
        this.setOffline=function (id) {
            var now=parseInt((new Date()).getTime()/1000);
            console.log("setting offline..");
            onlineTable[id]["endtime"]=now;
            console.log(onlineTable);
        }
        this.procFeedback=function(fbTable){
            var keys=Object.keys(onlineTable);  //取在线记录表的所有key
            var reject=0;   //是否被踢，0-无需操作，1-停止播放(心跳继续，只是停止播放，可建议跳到首页)，2-停止使用(停止心跳，只能关闭或刷新)
            for(var j=0, len=keys.length; j<len; j++){
                var k=keys[j];
                //console.log("procFeedback processing row:",fbTable[k]);
                //符合这个条件说明还是同一条记录。若不同了，说明原记录已经播放结束并重新播放了
                //若后端没传回BEid说明(新)记录未被处理，只有starttime>0的记录会被后端处理
                if((onlineTable[k]["starttime"]==fbTable[k]["starttime"]) && (fbTable[k]["BEid"]>0)){
                    //console.log("procFeedback matched row:",fbTable[k]);
                    //发送数据时是播放中的记录，现在状态可以忽略
                    if(fbTable[k]["endtime"]==0){
                        onlineTable[k]["BEid"]= fbTable[k]["BEid"]; //后台保证传来id
                        if(fbTable[k]["reject"]==true)  reject=(k=="web")? 2:1;
                        //console.log("reject",fbTable[k]["reject"],"k=",k);
                    }
                    else {
                        //发送数据时已经是播放结束的记录，清理，确认结束
                        onlineTable[k]["BEid"]=onlineTable[k]["starttime"]=onlineTable[k]["endtime"]=onlineTable[k]["FEobj"]=0
                    }
                }
            }
            console.log("procFeedback output table: ",onlineTable);
            return reject;
        }
        return this;
    }(); //在线记录对象

    /**
     * ////// 定义心跳对象 ///////
     * @param int 最大通讯间隔
     * @param function func 定时触发的函数
     */
    function Ou_KeepAlive(func,interval) {
        var timeoutHandle=null; //超时触发句柄
        interval=parseInt(interval)*1000;
        if(interval<1000) interval=10*1000;
        var aliveFunc=function () {
            func();
            if(null !=timeoutHandle) clearTimeout(timeoutHandle);
            timeoutHandle=setTimeout(aliveFunc,interval);
        }
        //重置触发器
        this.reset=function(){
            aliveFunc();
        }
        this.stop=function () {
            if(null !=timeoutHandle) clearTimeout(timeoutHandle);
        }
        this.reset();   //构造后启动触发器
        console.log("Ou_KeepAlive start.");
    }

    //预填写所有在线记录，整个播放前端只有3个在线记录且live，vod不能同时在线
    var now=parseInt((new Date()).getTime()/1000);
    Ou_OnlineTable.createOnline("web", params.onlineid, now, 0, "web",params.chnid,null); //填写web在线记录
    Ou_OnlineTable.createOnline("live",0, 0, 0, "live",params.chnid,null); //填写live在线记录
    Ou_OnlineTable.createOnline("vod", 0, 0, 0, "vod",0,null); //填写vod在线记录

    //////////与后端通讯相关//////////
    var communicate= new Ou_Communicate({"backend":params.appUrl+"/BE_communicate/server",
        "tokenName":"playToken",    //通讯令牌名称
        "tokenValue":params.playToken   //通讯令牌值
    });
    /**
     * 向服务器发送数据，自动附加onlineTable
     */
    this.send=function(data,callback){
        data["onlineTable"]=Ou_OnlineTable.getOnlineTable();
        data["appPara"]=appPara;
        communicate.send(data,callback);
    }

    //设置发送到服务端自动附加的应用参数
    //原来有的参数，若无新值传入不改变
    this.setAppPara=function(app, para){
        if("undefined"==typeof (appPara[app])) appPara[app]={};
        appPara[app] = $.extend(appPara[app],para);
//console.log("setAppPara: app=",app," para=",para," appPara=",appPara);
    }
    var keepalive=new Ou_KeepAlive(function (){  _this.send({});},params.aliveTime );    //params.aliveTime
    $(window).on("RecvData",function (event,recvdata){
        console.log("re................");
    });
    //响应收到服务器发送数据
    $(window).on("RecvData",function (event,recvData) {
        console.log("recvData proc onlineTable");
        //处理在线用户表
        if("object"==typeof(recvData.onlineTable)){
            var reject=Ou_OnlineTable.procFeedback(recvData.onlineTable);
            console.log("reject=",reject);
            if(1==reject){
                player.pause();
                Ou_OnlineTable.setOffline("vod");
                Ou_OnlineTable.setOffline("live");
                _this.send({});
                //$("#"+local.blkForceLayer).show();
                //TODO: 完善显示的内容
                //$("#"+local.blkForceLayer).html("强制停止播放");
                alert("已经暂停播放");
            }else if(2==reject){
                keepalive.stop(); //停止心跳
                //player.pause();
                player.dispose();
                Ou_OnlineTable.setOffline("web");
                Ou_OnlineTable.setOffline("vod");
                Ou_OnlineTable.setOffline("live");
                _this.send({});
                alert("您的账号从别的地方登录或被强制下线，若非本人操作，请立即修改密码！");
                window.location.href=params.homeUrl;    //关闭提示窗口后跳转到首页
                //$("#"+local.blkForceLayer).show();
                //TODO: 完善显示的内容
                //$("#"+local.blkForceLayer).html("强制退出本页");

            }
        }
    });

    //响应服务器丢失
    $(window).on("ServerLost",function (event) {
        player.dispose();
        $("#"+local.blkForceLayer).show();
        //TODO: 完善显示的内容
        $("#"+local.blkForceLayer).html("与服务器失去联系或通讯错误");
        keepalive.stop(); //停止心跳
    });

    ///////频道封面////////
    if(!params.showCover) $("#blkCover").hide();

    $("#btnEnterChannel").on("click",function () {
        $("#blkCover div").hide();
        $("#blkCover").animate({right:'100%'});
    });


    ///////////播放及播放器//////////
    /**
     * 初始化播放器
     *
     * @param string container  播放器容器DOM id
     * @param string playType    播放类型 [live|vod]
     * @param string source     播放地址
     * @param object options    可选参数将取代默认参数, 以下参数必须提供：
     *  - playType,source,cover
     * @return object 播放器对象
     */
    var initPlayer=function (container, options) {
        var playerOpt={
            "id": "prismPlayer",
            "width": "100%",  "height":"100%",
            "autoplay": false,
            "isLive": true, //false,
            //"skinLayout":false,
            "skinLayout": [
                { "name": "bigPlayButton", "align": "cc", "x": 30, "y": 80 },
                { "name": "H5Loading", "align": "cc" },
                { "name": "errorDisplay", "align": "tlabs", "x": 0, "y": 0 },
                { "name": "infoDisplay" },
                { "name": "tooltip", "align": "blabs", "x": 0, "y": 56 },
                { "name": "thumbnail" },
                {
                    "name": "controlBar", "align": "blabs", "x": 0, "y": 0,
                    "children": [
                        { "name": "progress", "align": "blabs", "x": 0, "y": 44 },
                        { "name": "playButton", "align": "tl", "x": 15, "y": 12 },
                        { "name": "timeDisplay", "align": "tl", "x": 10, "y": 7 },
                        { "name": "fullScreenButton", "align": "tr", "x": 10, "y": 12 },
                        //{ "name": "subtitle", "align": "tr", "x": 15, "y": 12 },
                        //{ "name": "setting", "align": "tr", "x": 15, "y": 12 },
                        //{ "name": "volume", "align": "tr", "x": 5, "y": 10 },
                        //{ "name": "snapshot", "align": "tr", "x": 10, "y": 12 }
                    ]
                }
            ],
            "rePlay": false,
            "playsinline": true,
            "preload": false,
            "cover":"/t/1.jpg",
            "controlBarVisibility": "hover",//控制面板的实现 ‘click’ 点击出现、‘hover’ 浮动出现、‘always’ 一直在
            "useH5Prism": true,
            //"x5_type":"h5",
            "x5_fullscreen":true,
            "x5_video_position":"top"
        }
        //playerOpt.id=container;
        //playerOpt.source=source;
        //playerOpt.cover=cover;
        playerOpt=$.extend(playerOpt,options);
//playerOpt.source="/vodfile/000/000/001/13288_5c9f740976775.mp4";    //for test

        if("live"==playerOpt.playType){
            playerOpt.isLive=true;
        }else{
            playerOpt.isLive=false;
        }

        return new Aliplayer(playerOpt,function (player) {
            console.log('player ready!');
            status.playerReady=true;
            //错误消息处理
            player.on("error",function (e) {
                console.log('player error!!====',e);
                Ou_OnlineTable.setOffline(playerOpt.playType);
                $(".prism-ErrorMessage").hide(); //隐藏出错信息
                player.setCover(params.cover);   //显示封面
            });
            player.on("play",function (e) {
                console.log("player play.====",playerOpt);
                $(local.layerVideoTop2).hide();
                if(playerOpt.isLive){
                    Ou_OnlineTable.setOnline("live","live",playerOpt.chnid,0);
                    Ou_OnlineTable.setOffline("vod");
                }else{
                    Ou_OnlineTable.setOnline("vod","vod",playerOpt.vodid,0);
                    Ou_OnlineTable.setOffline("live");
                }
            });
            player.on("pause",function (e) {
                console.log("player pause.====",playerOpt);
                Ou_OnlineTable.setOffline(playerOpt.playType);
            });
            player.on("ended",function (e) {
                console.log("player ended.====");
                Ou_OnlineTable.setOffline(playerOpt.playType);
            });
        });
    }
    var player=initPlayer("prismPlayer",params);

    this.reloadPlayer=function (type,mrl,cover,refid) {
        console.log("reloading player. type=",type,mrl,cover,refid);
        if(!status.playerReady) return;
        params.playType=type;
        params.source=mrl;
        params.cover=cover;
        if("live"==type) { params.chnid=refid; params.vodid=0; }
        else { params.vodid=refid; }
        if("object"==typeof player){
            player.pause();
            player.dispose();
        }
        player=initPlayer("prismPlayer",params);
    }
    this.getPlayer=function () {
        return  player;
    }

    //显示直播的播出时间以及倒计时
    var showAirTime=function () {
        if(params.airTime.length >2){
            //设置了开播时间
            //$(local.blkAirTime).html("开播时间："+ params.airTime);
            if(params.isAdmin !=1)  $(".blk_video .prism-big-play-btn").hide();
            //设置倒计时终止时间
            var endDate = new Date(params.airTime);
            var end = Math.floor(endDate.getTime()/1000);   //转换成秒的时间戳

            var airTimer=setInterval(function () {
                //获取当前时间
                var date = new Date();
                var now = Math.floor(date.getTime()/1000);

                //时间差
                var leftTime = end-now;
                //定义变量 d,h,m,s保存倒计时的时间
                var countDownStr,d,h,m,s;
                if(leftTime>0){

                    h=Math.floor(leftTime/3600);    //小时
                    if(h<10) h="0"+h;
                    m=Math.floor((leftTime%3600)/60);   //分
                    if(m<10) m="0"+m;
                    s=(leftTime%3600)%60;
                    if(s<10) s="0"+s;
                    countDownStr=h+":"+m+":"+s;
                    $(local.blkLeftTime).html("开播倒计时："+countDownStr);
                    if(params.isAdmin !=1)  $(".blk_video .prism-big-play-btn").hide();
                }else{
                    clearInterval(airTimer);
                    if(params.airDuration.length>1){
                        var duration=parseInt(params.airDuration);   //秒
                        if(end+duration>now){
                            //播出期间
                            $(".blk_video .prism-big-play-btn").show();
                        }else{
                            $(local.blkLeftTime).html("直播已结束");
                            if(params.isAdmin !=1)  $(".blk_video .prism-big-play-btn").hide();
                        }
                    }else{
                        $(".blk_video .prism-big-play-btn").show();
                    }

                }
            },1000);    //秒计数器
        }
    }

    //设置当前URL以便分享
    this.setUrl=function () {
        var  url="play.html?ch="+params.chnid;  //url=window.location.protocol+"//"+window.location.host+"/play.html?ch="+params.chnid;
        if(params.playType=="vod") url +="&vf="+params.vodid;
        if(params.uid>=100) url+="&du="+params.uid;
        console.log("url changed to=",url);
        history.replaceState(null,null,url+"#");    //当URL内有&时必须要在url后加点东西，否则在手机上会跳转
    }

    ///////////导航条处理对象/////////
    var tabs=function (tabPara) {
        console.log(tabPara);
        var activetab=tabPara.activetab;    //当前活动标签ID

        var tabBar=$("#"+tabPara.tabBar);   //导航条JQ对象
        var tabBlk=$("#"+tabPara.tabBlk);   //tab内容容器JQ对象

        //设置指定的tab为活动tab
        var setActive=function(tabOrder){
            var scrollWidth=tabBlk.width();
            console.log("scrollWidth="+scrollWidth);
            var tabid=tabBar.find(">div[tabOrder='"+tabOrder+"']").attr("tabid");
            tabid=parseInt(tabid);


            //处理界面
            tabBar.find(">div[tabid='"+activetab+"']").removeClass('tab-selected');
            activetab=tabid;
            tabBar.find(">div[tabid='"+activetab+"']").addClass('tab-selected');
            //$("#blk_func").scrollLeft(tabOrder*scrollWidth);
            status.tabScrolling=true;   //避免其它滚动事件响应
            $("#blk_func").animate({scrollLeft:(tabOrder*scrollWidth)},300,function () {
                //滚动完成再发送tab激活消息
                tabBlk.trigger("tabActive",[tabid,tabPara]);
                setTimeout(function(){status.tabScrolling=false;},100); //为了避免执行滚动事件响应
                //console.log("animate end");
            });
        }

        //导航条点击事件
        tabBar.on("click",function (event) {
            var obj = event.target;
            //console.log(obj);
            //var tabid=$(obj).attr("tabid");
            var tabOrder=$(obj).attr("tabOrder");
            setActive(tabOrder);
        });

        //左右拖动时,由于浏览器自带滑动特效，touchend后还会继续滚动，因此只能监听scroll事件，并延迟处理
        tabBlk.scroll(function() {
            console.log("scroll even");
            if(status.tabScrolling) return;
            clearTimeout($.data(this, 'scrollTimer'));
            $.data(this, 'scrollTimer', setTimeout(function() {
                var scrollLeft=tabBlk.scrollLeft(); //取滚动条位置
                var scrollWidth=tabBlk.width();
                var tabOrder=Math.round(scrollLeft/scrollWidth);
                setActive(tabOrder);
            }, 250));
        });

        //////////处理tab激活消息//////////
        /*
        101=>'视频直播', 102=>'互动聊天', 103=>'排行榜', 104=>'点播资源', 105=>'图片直播', 106=>'会员' ,107=>'分享',
            108=>'首页', 109=>'图文直播', 110=>'频道介绍');
        (501=>"送礼",502=>"抽奖",503=>"红包");	//频道使用的扩展功能
        */
        var isTabInit={};   //记录已经初始化的tab，{tabid:true,...}
        tabBlk.bind("tabActive",function(event,tabid,para) {

            var blkItem=$("#"+params.tabItemPrefix+tabid);
            console.log("recive event:"+tabid);
            switch (tabid){
                case 101:   //视频直播
                    console.log("101直播");
                    if(true != isTabInit[tabid]){
                        //未初始化，执行初始化
                        $.ajaxSetup({async:true});
                        blkItem.load(params.appUrl+"/Play/showChnInfo",{chnid:params.chnid});
                        isTabInit[tabid]=true;
                    }

                    //取直播播放地址及cover
                    console.log("取直播播放地址及cover");
                    var postData={"chnid":params.chnid, "agent":params.agent,"playToken":params.playToken};
                    $.post(params.appUrl+"/Play/getLiveSourceJson",postData,function (data) {
                        console.log("getLiveSourceJson",data);
                        if(null == data) alert("错误: 无法访问服务器");
                        else {
                            if (data.success != "true") alert(data.msg);
                            else {
                                _this.reloadPlayer("live",data.source,data.cover,params.chnid);
                            }
                        }
                    },"json");
                    _this.setUrl();
                    /*
                    var url=window.location.protocol+"//"+window.location.host;
                    url +="/play.html?ch="+params.chnid;
                    history.replaceState(null,null,url);
                    */
                    $(local.layerVideoTop2).show();
                    break;
                case 110:   //频道介绍
                    if(true != isTabInit[tabid]){
                        //未初始化，执行初始化
                        blkItem.load(params.appUrl+"/Play/showChnInfo",{chnid:params.chnid});
                        isTabInit[tabid]=true;
                    }
                    break;
                case 102:   //互动聊天
                    if(true != isTabInit[tabid]){
                        //未初始化，执行初始化
                        blkItem.load(params.appUrl+"/WebChat3/webChat",{channelId:params.chnid});
                        isTabInit[tabid]=true;
                    }
                    break;
                case 104:   //视频点播
                    if(true != isTabInit[tabid]){
                        //未初始化，执行初始化
                        blkItem.load(params.appUrl+"/Play/vodlist",{chnid:params.chnid,vodid:params.vodid,playToken:params.playToken});
                        isTabInit[tabid]=true;
                    }
                    break;
                case 105:   //图片直播
                    if(true != isTabInit[tabid]){
                        //未初始化，执行初始化
                        blkItem.load(params.appUrl+"/Channel/showPhoto",{chnId:params.chnid});
                        isTabInit[tabid]=true;
                    }
                    break;
                case 106:   //会员
                    if(true != isTabInit[tabid]){
                        //未初始化，执行初始化
                        blkItem.load(params.appUrl+"/My/showMyInfo/chnid/"+params.chnid);
                        isTabInit[tabid]=true;
                    }
                    break;
                case 109:   //图文直播
                    if(true != isTabInit[tabid]){
                        //未初始化，执行初始化
                        blkItem.html("");
                        $("#pictxt").appendTo(blkItem);
                        $("#pictxt").load(params.appUrl+"/FE_PicTxt/init",{chnid:params.chnid, programid:params.vodid});
                        $("#pictxt").show();
                        isTabInit[tabid]=true;
                    }
                    break;
            }
        });
        //其它扩展的tab功能
        tabBlk.bind("tabActive",function(event,tabid,para) {
            //console.log(tabid);
            //console.log(para);
        });

        //初始化，必须要放在此对象的最后
        var activeOrder=tabBar.find(">div[tabid='"+activetab+"']").attr("taborder");  //addClass('tab-selected');
        if(typeof(activeOrder)=="undefined") activeOrder=0;
        setActive(activeOrder);
    }({ tabBar:"tabBar",tabBlk:"blk_func",activetab:params.activetab });

    //////////强制操作层处理///////////
    switch (params.forceLayer){
        case "register":
            //$("#"+local.blkForceLayer).load("Home.php/My/chnRegiste/chnid/1098");
            var para={"chnid":params.chnid,"vidid":params.vodid, "tab":params.activetab, "agent":params.agent};
            $("#"+local.blkForceLayer).load(params.appUrl+"/Play/showChnRegister",para);
            break;
    }
    if(params.forceLayer!="hide") $("#"+local.blkForceLayer).show();
    else $("#"+local.blkForceLayer).hide();

    this.popup=function (notify,button,callback) {
        var popup=$("#win_popup");
        if(null!=notify) popup.find(".win_notify").html(notify);
        if(null!=notify) popup.find("button").html(button);
        popup.show();
        popup.find("button").on("click",function () {
            console.log("popup click");
            popup.find("button").off("click");
            popup.hide();
            if("function"==typeof callback) callback();
        });
    }

    /////// 防挂机 //////
    !function(interval){
        var defaultInterval=3600;    //默认挂机(秒)
        var warmTime=60;        //提前警告时间
        var countdownInterval=10;   //减法计数时间间隔(秒)

        interval=parseInt(interval);
        if(interval<warmTime*2) interval=defaultInterval;
        var countdown=interval;

        //10秒做一次减法计数
        var idleTimrer=setInterval(function () {
            countdown -= countdownInterval;
            console.log("idleTimrer countdown=",countdown);
            if(countdown<=warmTime){
                //弹出警告窗口
                _this.popup("您已经观看很久了，请休息一会","再看一会儿",function () {
                    countdown=interval;
                });
            }
            if(countdown<0){
                console.log("foce out===");
                keepalive.stop(); //停止心跳
                clearInterval(idleTimrer);
                player.dispose();
                Ou_OnlineTable.setOffline("web");
                Ou_OnlineTable.setOffline("vod");
                Ou_OnlineTable.setOffline("live");
                _this.send({});
                _this.popup("您太久没活动了，已被强制退出","到首页看看",function () {
                    window.location.href=params.homeUrl;    //关闭提示窗口后跳转到首页
                });
            }
        },countdownInterval*1000);
        $(window).on("touchstart click",function () {
            countdown=interval;
        });
    }(params.operatorIdleInt);

    //取公共参数
    this.getParam=function () {
        return params;
    }

    //页面初始时运行的东西集中在这里
    var initPage=function () {

        //若是直播，显示开播信息
        if(params.playType=="live") showAirTime();
        //修改页面标题
        $("title").text(params.title);
    }
    setTimeout(function () {
        initPage();
    },1);
    // return this;
}


