/**
 * 网页聊天对象控制类
 * 
 * 依赖：jquery, jeasyui
 */
////// webChat object //////
function webChat(){
	//以下属性在执行init方法之前必须正确赋值
	this.continer=null;	//对象容器id
	this.channelId=null;	//频道Id	
	this.userName=null;	//用户昵称
	this.userId=null;	//用户Id
	this.objName=null;	//聊天实例对象名称
	this.appUrl=null;	//聊天模块入口文件url
	
	//对象内使用，无需预先属性
	this.lastMsgId=null;	//当前读取到最后一条信息的Id
	this.isLoad=false;	//对话对象是否已经装入
	this.timer=null;		//定期更新信息的定时器句柄
	this.canSend=false;		//是否有权发言
}

/**
 * 清除聊天对象的HTML内容，停止定时更新
 */
webChat.prototype.unload=function(){
//console.log('unload');	
	//清除定时器
	if(null != this.timer) {
		clearInterval(this.timer);
		this.timer=null;
	}
	var jcontiner="#"+this.continer;
	this.isLoad=false;
	$(jcontiner).html('');
}

/**
 * 向服务端发送新的聊天记录
 */
webChat.prototype.sendMsg=function(){
	//console.log('new message.');
	
	if(!this.canSend)
	{
		alert('您无权发言！您没有登录或被禁言');
	}

	var instance=this;
	var para={channelId:this.channelId, userId:this.userId,userName:this.userName,objName:this.objName,lastMsgId:this.lastMsgId };
	para.message=$.trim($('#message').textbox('getText'));

	if(para.message.length<1) return false;	//没信息不提交
	
	$.post(this.appUrl+"/WebChat/newMsg", para, function(data){
		if(''!=data.html){
			$('#chatMsg').append(data.html);
			$('#chatMsg').scrollTop($("#chatMsg")[0].scrollHeight);	//滚动到底部
		}
		if('lastMsgId' in data)instance.lastMsgId=data.lastMsgId;	//最新信息Id
		$('#message').textbox('clear');
	}, "json");
	return;
}


/**
 * 初始化对象，把对应的HTML嵌入到对象容器中
 */
webChat.prototype.init=function(){
	var para={channelId:this.channelId, userId:this.userId,userName:this.userName,objName:this.objName };
	var jcontiner="#"+this.continer;
	var instance=this;
//console.log(instance.timer);
//console.log('loading chat to:'+jcontiner);	
	if(null != instance.timer) { 
		clearInterval(instance.timer);
		instance.timer=null;
	}
//console.log(instance.timer);
	$.post(this.appUrl+"/WebChat/webChat", para, function(data){
		try{
			if('true'!=data.success) throw new Error('初始化失败！');
			if(null != data.html)
			{
				$(jcontiner).html(data.html);
			}

			if('lastMsgId' in data)instance.lastMsgId=data.lastMsgId;	//最新信息Id
//console.log('lastMsgId='+instance.lastMsgId.toString());			
			$.parser.parse(jcontiner);	//jeasyui渲染
			instance.isLoad=true;
			instance.fit();
//console.log('isLoaded='+instance.isLoad);
			//首先获取一次更新信息
			setTimeout(instance.updateChatMsg(), 500);
			if(null == instance.timer )	//保证不存在多个定时器
				instance.timer=setInterval(function(){instance.updateChatMsg();},10000);	//10秒刷新一次
		}catch(e){
			//alert(e.message);
		}
	}, "json");
}

/**
 * 定时读入新的聊天记录
 */
webChat.prototype.updateChatMsg = function(){
	//console.log('updateChat');	
	var display=$('#'+this.continer).css('display');
	//console.log(display);
	if(!this.isLoad || 'none'==display) return;

	try
	{
		var state=$('#autoUpdate').linkbutton('options');	//未选中为false，选中为true
		if(state.selected) return;
	}
	catch (e)
	{
	}

	var instance=this;
	var para={channelId:this.channelId, userId:this.userId,userName:this.userName,objName:this.objName,lastMsgId:this.lastMsgId };
	$.post(this.appUrl+"/WebChat/updateChatMsg",para,
		function(data){
			if(''!=data.html){
				$('#chatMsg').append(data.html);
				$('#chatMsg').scrollTop($("#chatMsg")[0].scrollHeight);	//滚动到底部
			}
			if('lastMsgId' in data)instance.lastMsgId=data.lastMsgId;	//最新信息Id

			instance.canSend = data.isCanChat;

			if(true == data.isCanChat)
			{
				$('#sendBtn').linkbutton('enable');
			}
			else
			{
				$('#sendBtn').linkbutton('disable');
			}
		}
	,'json');
}
/**
 * 调整聊天窗口大小，使之适应容器
 */
webChat.prototype.fit = function() {
	var display=$('#'+this.continer).css('display');
	if(!this.isLoad || 'none'==display) return;	//未装载或不可见忽略
	
	var	jcontiner=$('#'+this.continer);	//聊天容器的jquery对象
	var continerHeight=jcontiner.height();
//console.log('fit continerHeight='+continerHeight.toString());	
	var continerWidth=jcontiner.width();
//console.log('fit continerWidth='+continerWidth.toString());	

	//设置聊天信息窗口高度
	$("#chatMsg").height(continerHeight-$('#newMsg').outerHeight(true));
	$('#chatMsg').scrollTop($("#chatMsg")[0].scrollHeight);	//滚动到底部

	//设置新信息输入框宽度
	//var btnWidth=$('#sendBtn').outerWidth(true);
	//$('#message').textbox({width: continerWidth-btnWidth-30});
}

webChat.prototype.clsMsg = function()
{
	$('#chatOptMsg').text('');
}

/**
 * 发送禁言指令
 */
webChat.prototype.noChat = function(uid)
{
	//console.log('chnid' + chnId + 'uid' + uid);
	var instance=this;
	var para={channelId:this.channelId, userId:this.userId, nochatuid:uid };
	
	$.post(this.appUrl+"/WebChat/noChat", para, function(data){
		//success
		if('true' == data.success)
		{
			$('#chatOptMsg').text('禁言成功！');
			setTimeout('chat.clsMsg()', 3000);
			//msgShow('message','success', 1000);
			//alert('end');
		}
	}, "json");
	return;

}

////// end of webChat object //////