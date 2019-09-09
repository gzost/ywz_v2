/**
 * 网页聊天对象控制类
 * 
 * 依赖：jquery, jeasyui
 */
////// webChat object //////
function webChat(){
	var _this=this;
	//以下属性在执行init方法之前必须正确赋值
	this.continer=null;	//对象容器id
	this.channelId=null;	//频道Id	
	this.userName=null;	//用户昵称
	this.userId=null;	//用户Id
	this.objName=null;	//聊天实例对象名称
	this.appUrl=null;	//聊天模块入口文件url
	
	//对象内使用，无需预先属性
	this.lastMsgId=null;	//当前读取到最后一条信息的Id
	this.firstMsgId=null;	//当前已读取的第一条信息的Id
	var firstPageLoaded=false;	//已读入第一页
	this.isLoad=false;	//对话对象是否已经装入
	this.timer=null;		//定期更新信息的定时器句柄
	this.canSend=false;		//是否有权发言

	//私有变量
	var lastEditRange;	// 定义最后光标对象

   this.scrollFunc=function () {
       var scrollTop = $("#chatMsg").scrollTop();
       //console.log('scrollTo:' + scrollTop);
       if (0 == scrollTop) {
           //已滚动到容器的顶部
           if (true === firstPageLoaded) {
               //已经没有更早的聊天记录可装入了。
               $("#chatMsg").prepend("<div class='loadmsg'>没有更多的数据了</div>");
               var loadmsg=$("#chatMsg .loadmsg");
               loadmsg.fadeOut(2000,function(){
                   loadmsg.remove();
			   });
           }else{
               $("#chatMsg").prepend("<div class='loadmsg'>正在读入数据...</div>");
               $.ajaxSettings.async = false;   //设置为同步调用
               var para={channelId:_this.channelId, userId:_this.userId,userName:_this.userName,objName:_this.objName,lastMsgId:_this.lastMsgId,firstMsgId:_this.firstMsgId };
               $.post(_this.appUrl+"/WebChat/getPrePageJson", para, function(data){
                   $("#chatMsg .loadmsg").remove();
                   if(''!=data.html){
                       $('#chatMsg').prepend(data.html);
                   }
                   if('firstMsgId' in data) _this.firstMsgId=data.firstMsgId;	//最新信息Id
                   if('firstPageLoaded' in data) firstPageLoaded=data.firstPageLoaded;
                   $("#chatMsg").scrollTop(5);
               }, "json");
		   }
       }
   }
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
webChat.prototype.sendMsg=function(msg){
	//console.log('new message.');
	
	if(!this.canSend)
	{
		alert('您无权发言！您没有登录或被禁言');
	}

	var instance=this;
	var para={channelId:this.channelId, userId:this.userId,userName:this.userName,objName:this.objName,lastMsgId:this.lastMsgId };
	//para.message=$.trim($('#message').textbox('getText')); 2019-07-24 outao
    para.message=msg;

	if(para.message.length<1) return false;	//没信息不提交
	
	$.post(this.appUrl+"/WebChat/newMsg", para, function(data){
		if(''!=data.html){
			$('#chatMsg').append(data.html);
			$('#chatMsg').scrollTop($("#chatMsg")[0].scrollHeight);	//滚动到底部
		}
		if('lastMsgId' in data)instance.lastMsgId=data.lastMsgId;	//最新信息Id
		//$('#message').textbox('clear');	2019-07-24 outao
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
            if('firstMsgId' in data)instance.firstMsgId=data.firstMsgId;
//console.log('lastMsgId='+instance.lastMsgId.toString());			
			$.parser.parse(jcontiner);	//jeasyui渲染
			instance.isLoad=true;
			instance.fit();
//console.log('isLoaded='+instance.isLoad);
			//首先获取一次更新信息
			setTimeout(instance.updateChatMsg(), 500);
			if(null == instance.timer )	//保证不存在多个定时器
				instance.timer=setInterval(function(){instance.updateChatMsg();},10000);	//10秒刷新一次
            //instance.setLastEditRange();
			//处理滚动消息，为装入旧聊天信息
            var chatMsgObj=$("#chatMsg");
            chatMsgObj.on("scroll",instance.scrollFunc );
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
	var para={channelId:this.channelId, userId:this.userId,userName:this.userName,objName:this.objName,lastMsgId:this.lastMsgId,firstMsgId:this.firstMsgId };
	$.post(this.appUrl+"/WebChat/updateChatMsg",para,
		function(data){
console.log(data);
			if(''!=data.html){
				$('#chatMsg').append(data.html);
				$('#chatMsg').scrollTop($("#chatMsg")[0].scrollHeight);	//滚动到底部
			}
			if('lastMsgId' in data)instance.lastMsgId=data.lastMsgId;	//最新信息Id
            if('firstMsgId' in data)instance.firstMsgId=data.firstMsgId;

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
/*
webChat.prototype.clsMsg = function()
{
	$('#chatOptMsg').text('');
}
*/
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
			$.messager.show({title:"操作结果",msg:"禁言成功",timeout:3000});
			return;
			//$('#chatOptMsg').text('禁言成功！');
			//setTimeout('chat.clsMsg()', 3000);
			//msgShow('message','success', 1000);
			//alert('end');
		}
	}, "json");
	return;
}

/**
 * 在输入框对象的光标位置插入文本或HTML语句
 * @param string text	要插入的文本
 * @param objiect edit	输入框对象
 */
webChat.prototype.insertText= function(text, edit){
    var emojiInput={value:text};
    // 编辑框设置焦点
    edit.focus()
    // 获取选定对象
    var selection = getSelection()
    // 判断是否有最后光标对象存在
    if (lastEditRange) {
        // 存在最后光标对象，选定对象清除所有光标并添加最后光标还原之前的状态
        selection.removeAllRanges()
        selection.addRange(lastEditRange)
    }
    // 判断选定对象范围是编辑框还是文本节点
    //console.log(selection.anchorNode);
    if (selection.anchorNode.nodeName != '#text') {
        // 如果是编辑框范围。则创建表情文本节点进行插入
        var emojiText = document.createTextNode(emojiInput.value)
        if (edit.childNodes.length > 0) {
            // 如果文本框的子元素大于0，则表示有其他元素，则按照位置插入表情节点
            for (var i = 0; i < edit.childNodes.length; i++) {
                if (i == selection.anchorOffset) {
                    edit.insertBefore(emojiText, edit.childNodes[i])
                }
            }
        } else {
            // 否则直接插入一个表情元素
            edit.appendChild(emojiText)
        }
        // 创建新的光标对象
        var range = document.createRange()
        // 光标对象的范围界定为新建的表情节点
        range.selectNodeContents(emojiText)
        // 光标位置定位在表情节点的最大长度
        range.setStart(emojiText, emojiText.length)
        // 使光标开始和光标结束重叠
        range.collapse(true)
        // 清除选定对象的所有光标对象
        selection.removeAllRanges()
        // 插入新的光标对象
        selection.addRange(range)
    } else {
        // 如果是文本节点则先获取光标对象
        var range = selection.getRangeAt(0)
        // 获取光标对象的范围界定对象，一般就是textNode对象
        var textNode = range.startContainer;
        // 获取光标位置
        var rangeStartOffset = range.startOffset;
        // 文本节点在光标位置处插入新的表情内容
        textNode.insertData(rangeStartOffset, emojiInput.value)
        // 光标移动到到原来的位置加上新内容的长度
        range.setStart(textNode, rangeStartOffset + emojiInput.value.length)
        // 光标开始和光标结束重叠
        range.collapse(true)
        // 清除选定对象的所有光标对象
        selection.removeAllRanges()
        // 插入新的光标对象
        selection.addRange(range)
    }
    // 无论如何都要记录最后光标对象
    lastEditRange = selection.getRangeAt(0)
}

// 设置最后光标对象
webChat.prototype.setLastEditRange=function(){
    // 获取选定对象
    var selection = getSelection()
    // 设置最后光标对象
    lastEditRange = selection.getRangeAt(0)
}

////// end of webChat object //////