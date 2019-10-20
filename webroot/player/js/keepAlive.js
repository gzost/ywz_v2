/**
 * 保持心跳相关功能
 */

function HeartBeat()
{
	var _this = this;	//私有属性，指向当前实例

	//定义public属性

	//维持心跳
	_this.keepAlive = function ()
		{
			//var status=eval('({ onlineId:'+onlineId.toString()+'}) ');
			var status={ "onlineList":onlineList };
			console.log(status);

			$.ajax({
				url: keepAliveUrl,
				type: 'post',
				data: status,
				cache:false,
				timeout:15000,	//超时设为15秒
				dataType: 'json',
				success:function(json){
					//返回数据格式：[{"type":"数据分类名称","data":{} }, ...]
					console.log("keepAlive return=======");
					console.log(json);
					try{
						//typeof返回值：number, boolean, string, undefined, object, function,symbol.
						if('object'!=typeof (json)) throw new Error('服务器返回错误！');
                        //for(let item of json){
                        for(var i=0, len=json.length; i<len; i++){
                        	var item=json[i];
                        	//console.log(item);
                        	//console.log(typeof(item.type));
                            if('string'!=typeof (item.type) || 'undefined'==typeof(item.data)) throw new Error('数据格式错误');
                            $(window).trigger(item.type,[item.data]);	//发出消息，调用其它处理函数
                        }
                        $(window).trigger('HeartBeatSuccess',[json]);	//发送心跳成功信息
                        console.log('HeartBeatSuccess');
					}catch (e) {
						console.log(e.message);
                    }
				},
				complete:function(){	//无论是否成功都会调用
                    setTimeout(_this.keepAlive,15000);	//心跳间隔15秒
				},
				error:function(){
					//网络中断了就不可能跳转了。这里无需处理，另外设置定时器，当一段时间没收到HeartBeatSuccess消息则判断网络中断
					console.log('HeartBeatError, network broken. ')	;
				}
			});
			//这里很可能比post语句更早执行
		}

	//弹出提示框延时5秒强制退出
	_this.forceLogout = function(msg, t){
		if(null == t) t = 10000;
		if(null != this.onShowMsg)
		{
			this.onShowMsg(msg);
		}
		if(null != this.onMsgExit)
		{
			this.onMsgExit(msg);
		}
		setTimeout(_this.doLogout, t);
	}

	//关闭倒计时，并退出
	_this.doLogout = function(){
		$.messager.progress('close');
		window.location.replace(logoutUrl);
	}

	/**
	 * 异步增添在线记录
	 * 
	 * @param objType	在线对象类型
	 * @param objId		在线对象ID
	 * @param onSuccess	成功时的回调函数function(onlineId,data)。 data包含服务端返回的全部数据。无需回调时填null。
	 * @param onError	失败时调用function(msg,data)。msg:错误信息；data:从服务器返回的全部数据。无需回调时填null。
	 * 					回调的data参数可能为空。
	 * @returns 成功或失败分别调用回调函数
	 */
	_this.startOnline = function (objType,objId,onSuccess,onError,chnId){
			console.log('startOnline:'+objType);
			console.log(typeof onSuccess);

			$.ajax({
				url: startOnlineUrl,
				type: 'post',
				data: {"objType":objType, "objId":objId, "chnId":chnId},
				cache:false,
				dataType: 'json',
				success:function(data){
					var onlineId=0;
					try{
						if(typeof(data)!="object" || null==data ) throw new Error("Return data formet error!");
						if(data.success !='true') throw new Error("Recived a error!");
						if(null==data.onlineId) throw new Error("No onlineId !");
						//正常返回
						onlineId=data.onlineId;
						if(1>onlineId) throw new Error("Wrong onlineId !");
						
						var i=onlineList.length;
						while(i--){
							if(onlineId==onlineList[i].onlineId) throw new Error("duplicated onlineId !");
						}
						onlineList.push({"onlineId":onlineId,"objType":objType, "objId":objId});
						if(null!= data.multiOnline && data.multiOnline>1 ) throw new Error("您同时的登录次数超过了预设值！");
						if(typeof(onSuccess)=="function"){
							onSuccess(onlineId, data);	//回调成功处理
						}
					} catch(e){
						if(typeof(onError)=="function"){
							onError(e.message, data);	//回调出错处理
						}
						return;
					}
					return;

				},
				complete:function(){	//无论是否成功都会调用
					//alert('complete');
					//this.enableKeepAlive=true;
				},
				error:function(){
					if(typeof(onError)=="function"){
						onError('No Response.', '');	//回调出错处理
					}
					return;
				}
			});
		}

	_this.stopOnline = function (onlineId,callBack){
			console.log('stopOnline:'+onlineId.toString());
        //删除前端存储的在线列表。
        var i=onlineList.length;
        while(i--){
            if(onlineId==onlineList[i].onlineId) {
                onlineList.splice(i,1);	//删除当前数组元素
                break;
            }
        }
			//this.enableKeepAlive=false;
			$.ajax({
				url: stopOnlineUrl,
				type: 'post',
				data: {"onlineId":onlineId},
				cache:false,
				dataType: 'json',
				success:function(data){
					var retMsg='success';
					try{
						if(typeof(data)!="object" || null==data ) throw new Error("Return data formet error!");
						if(data.success !='true') throw new Error("Recived a error!");


					} catch(e){
						retMsg=e.message;
					}
					if(typeof(callBack)=="function"){
						callBack(retMsg, data);	//回调成功处理
					}
					return;

				},
				complete:function(){	//无论是否成功都会调用
					//alert('complete');
					//this.enableKeepAlive=true;
				},
				error:function(){
					if(typeof(onError)=="function"){
						onError('No Response.', '');	//回调出错处理
					}
					return;
				}
			});
		}

	_this.onShowMsg = null;
	_this.onMsgExit = null;

	//处理心跳后从后台返回的online指令
	_this.showForceOutUrl="showForceOut";	//被强制退出后的页面，用于提示用户被强制退出的原因，及提供下一步的参考。可配置此属性
	$(window).on('online',function (event,data) {
		//data为数组,[{'id':在线ID, action:'被后台要求的操作',msg:用于显示的消息}]
		//action:通知前端的动作，不定义或none则前端无需处理，reject-退出登录，sopt-停止播放
        console.log('proc online');
        console.log(data);
        /*
        for(let row of data){
        	console.log((row.action));
        	if('string'!=typeof(row.action)) continue;
			if('reject'==row.action || 'stop'==row.action) {
				var msg=('string'==typeof(row.msg))?row.msg:'';
				window.location.replace(_this.showForceOutUrl+"?msg="+msg);
            }	//被强制退出的跳转
        }
        */
        for(var j = 0,len = data.length; j < len; j++){
            console.log(data[j]);
            if('string'!=typeof(data[j].action)) continue;
            if('reject'==data[j].action || 'stop'==data[j].action) {
                var msg=('string'==typeof(data[j].msg))?data[j].msg:'';
                window.location.replace(_this.showForceOutUrl+"?msg="+msg);
            }	//被强制退出的跳转
        }
    });

	//看门狗:每10秒所有看门狗变量-1，当变量到达0时，看门狗程序发出
	const netBrokenInt=6*2;	//网络中断最长时间，初始值，2分钟
	var netBroken=netBrokenInt;	//网络中断计数值，此值=0时发出netBroken消息，每次心跳成功消息时把他设为初始值，这样当一段时间心跳失败时，判断为网络中断
	const operatorIdleInt=6*60;	//播放终端最长不操作时间，初始值60分钟
	var operatorIdle=operatorIdleInt;	//当终端出现键盘，鼠标，滚动等操作时，把此值设为初始值
	var watchdogId =setInterval(function(){
		console.log('watchdog:'+netBroken+'-'+operatorIdle);
		if(0==netBroken){
			console.log('trigger netBroken');
            $(window).trigger('netBroken');
            netBroken=netBrokenInt;
		}else netBroken--;

        if(0==operatorIdle){
            console.log('trigger operatorIdle');
            $(window).trigger('operatorIdle');
            operatorIdle=operatorIdleInt;
        }else operatorIdle--;
	},10000);	//10秒定时触发

	$(window).on('HeartBeatSuccess',function(){
        netBroken=netBrokenInt;
    });
	$(window).on('keydown mousemove scroll',function () {
		console.log('active');
        operatorIdle=operatorIdleInt;
    });

	//发生超时没动作时处理，防挂机
	$(window).on('operatorIdle',function () {
		var idleTimer=setTimeout(function () {
            window.location.replace(_this.showForceOutUrl+"?msg=您看得太久没活动了，需要休息一下。");
        },30000);	//30秒没动作跳转
        $.messager.defaults = { ok: "不看了", cancel: "继续看" };
        $.messager.confirm('提示', '您看了很久了，需要休息一下吗? ', function(r){
            if (!r){
               clearTimeout(idleTimer);
            }else{
                window.location.replace(_this.showForceOutUrl+"?msg=待会再见！");
			}
        });
    });

	//连续心跳无法访问服务器，可能网络中断或被拦截心跳
	//网络不通不能从服务器拿网页了。
	var netBrokenHtml="<div style=\"height: 100%; width: 100%; background-color: #aaa; color:#111; font-size: 24px;\">\n" +
        "    <div style=\"position:fixed; top:250px; width:100%; padding: 10px 10px; background-color: #fff;display:table-cell;vertical-align:middle;\">\n" +
        "        <div style=\"float: left; font-size: 3em; padding:20px 10px;\">\n" +
        "            ☹\n" +
        "        </div>\n" +
        "       <br>与服务器失去联系了。\n" +
        "    </div>\n" +
        "</div>";
	var netBrokenCounter=10;	//网络中断被调用若干次后，强制跳转若真的中断页面会破坏。
    $(window).on('netBroken',function () {
    	if(0 == --netBrokenCounter){
            window.location.replace("http://www.av365.cn/Home.php/Home/index");
		}
        $("html").html(netBrokenHtml);	//尽管覆盖了HTML但只是影响了显示，JS还是在运行的
    });

	return this;
}
