/**
 * 保持心跳相关功能
 */

 var _HB = null;

function HeartBeat()
{
	_HB = this;

	//允许心跳没响应或错误响应的次数
	this.aliveBlood = 3;

	//是否允许发送心跳。防止在更新在线记录期间心跳处理的冲突。
	//this.enableKeepAlive = true;

	//this.aliveTime = 30;

	//维持心跳
	this.keepAlive = function ()
		{
			var self = this;
			console.log('keepAlive1,blood=' + this.aliveBlood);
			/*
			if(!this.enableKeepAlive)
			{
				console.log('keepAlive disabled.');
				setTimeout("_HB.keepAlive();",10000);
				return;
			}
			*/
			//var status=eval('({ onlineId:'+onlineId.toString()+'}) ');
			var status={ "onlineList":onlineList };
			console.log(status);	
			this.aliveBlood--;
			
			$.ajax({
				url: keepAliveUrl,
				type: 'post',
				data: status,
				cache:false,
				timeout:15000,	//超时设为15秒
				dataType: 'json',
				success:function(json){
					var command='';
					var isReject='false';
					var isFresh='false';
					try{
						if( null==json ) throw new Error("服务器或网络错误!");
						if( 'false' == json.result ) throw new Error("您不应该在线。");
						if( 'true' == json.reject ) throw new Error("您已被强制下线。");
						if( 'true' == json.rejectTO ) throw new Error("您观看的时间太长了，已被强制下线。");
					} catch(e){
						_HB.forceLogout(e.message);	//
						return;
					}
					//正常情况
					self.aliveBlood=3;	//计数恢复原始值
					//alert('success');
				},
				complete:function(){	//无论是否成功都会调用
					
					
					//alert('complete');
				},
				error:function(){
					if(self.aliveBlood<1) self.forceLogout('网络或服务中断！');
					console.log('Net broken blood=' + self.aliveBlood)	;
					//alert('error');
				}
			});
			//这里很可能比post语句更早执行

			setTimeout("_HB.keepAlive();",30000);
		}

	//弹出提示框延时5秒强制退出
	this.forceLogout = function(msg, t){
		if(null == t) t = 10000;
		if(null != this.onShowMsg)
		{
			this.onShowMsg(msg);
		}
		if(null != this.onMsgExit)
		{
			this.onMsgExit(msg);
		}
		setTimeout(_HB.doLogout, t);
	}

	//关闭倒计时，并退出
	this.doLogout = function(){
		//$.messager.progress('close');
		//window.location.replace(logoutUrl);
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
	this.startOnline = function (objType,objId,onSuccess,onError){
			console.log('startOnline:'+objType);
			console.log(typeof onSuccess);

			$.ajax({
				url: startOnlineUrl,
				type: 'post',
				data: {"objType":objType, "objId":objId},
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

	this.stopOnline = function (onlineId,callBack){
			console.log('stopOnline:'+onlineId.toString());	
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

						//正常返回，删除前端存储的在线列表。
						var i=onlineList.length;
						while(i--){
							if(onlineId==onlineList[i].onlineId) {
								onlineList.splice(i,1);	//删除当前数组元素
								break;
							}
						}
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

	this.onShowMsg = null;
	this.onMsgExit = null;
	return this;
};






