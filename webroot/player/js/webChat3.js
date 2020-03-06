/**
 * 网页聊天对象控制类
 * 
 * 依赖：jquery
 * @param object options	实例化时修改可默认属性值，
 */
function webChat(options){
    var _this=this;
    var defaults={
    	//无默认，实例化时必须提供的属性
    	channelId: 0,	//频道Id
    	userName: "",	//用户昵称
    	userId: 0,	//用户Id
        canChat: false,		//是否有权发言
    	//appUrl: null,	//聊天模块入口文件url
		webpage: playPage,	//页面的公共对象，提供如发送消息等的公共支持
        lastMsgId:0,	//当前读取到最后一条信息的Id
        firstMsgId:0,	//当前已读取的第一条信息的Id
        firstPageLoaded:false,	//已读入第一页
        unresponsive:false, //忽略滚动消息
		//其它属性
        continer:".wechat-container"	//对象容器
    }
    var params = $.extend(defaults,options);	//整合实例化的参数

    //与页面相关属性都集中这里定义，降低JS与页面的耦合
	var page={
		blk_chatMsg:"#chatMsg",
        chatItem:"#msgItemTpl .chat-item"
	}

    var scrollFunc=function () {
        var scrollTop = $(page.blk_chatMsg).scrollTop();
        //console.log('scrollTo:' + scrollTop);
        if (0 == scrollTop && !params.unresponsive) {
            //已滚动到容器的顶部
            params.unresponsive=true;   //暂时不响应滚动消息
            if (true === params.firstPageLoaded) {
               //已经没有更早的聊天记录可装入了。
               $(page.blk_chatMsg).prepend("<div class='loadmsg'>没有更多的数据了</div>");
                $(page.blk_chatMsg).off("scroll",scrollFunc );
                /*
               var loadmsg=$(page.blk_chatMsg+" .loadmsg");
               loadmsg.fadeOut(2000,function(){
                   loadmsg.remove();
                   params.unresponsive=false;
			   });*/
            }else{
               $(page.blk_chatMsg).prepend("<div class='loadmsg'>正在读入数据...</div>");
                var loadmsg=$(page.blk_chatMsg+" .loadmsg");
                loadmsg.fadeOut(2000,function(){
                    loadmsg.remove();
                    params.unresponsive=false;
                });
                updateMsg("prev");
		    }
        }
    }

    //向显示模板填写内容
    var fillItem=function(rec){
		var chatItem=$(page.chatItem);	//消息显示项jq对象
        var sendtime=new Date(rec.sendtime);
        var today=(new Date()).toDateString();
//console.log("sendtime=",sendtime,"today=",today,sendtime.toDateString()==today);
        var datestr=(sendtime.toDateString()==today)?rec.sendtime.substr(11,5):rec.sendtime.substr(5,11);
	    chatItem.attr({msgid:rec.id, senderid:rec.senderid});
        chatItem.find(".left-box").attr("sender",rec.senderid);
	    chatItem.find(".right-box .msg-info span").html(rec.sendername);
        chatItem.find(".right-box .msg-info div").html(datestr);
        chatItem.find(".right-box .msg-text xmp").html(rec.message);

    }

   //处理后台发来的数据
   $(window).on("RecvData",function (event,recvdata) {
        console.log("updateMsg recv:",recvdata,"type of chat:",typeof recvdata.chat);
        if("object"!= typeof recvdata.chat) return;
        var chatObj=recvdata.chat;

        if(chatObj.lastMsgId > params.lastMsgId) params.lastMsgId=chatObj.lastMsgId;    //最新新记录ID
        if(params.firstMsgId==0 || chatObj.firstMsgId<params.firstMsgId) params.firstMsgId=chatObj.firstMsgId; //最早记录ID

       //更新通讯模块中，聊天应用的参数
        var pkg={lastMsgId:params.lastMsgId, firstMsgId:params.firstMsgId}
        playPage.setAppPara("chat",pkg);

        if(chatObj.total==0 && chatObj.action=="prev") params.firstPageLoaded=true; //没用更早的记录了

	    if(chatObj.total>0){
	    	var rows=chatObj.rows;
            var chatItem=$(page.chatItem);
            if(chatObj.action=="prev"){
                //头部插入信息
                for(var i=0, len=chatObj.total; i<len; i++) {
                    fillItem(rows[i]);
                    chatItem.clone().prependTo(page.blk_chatMsg);
                }
                $(page.blk_chatMsg).scrollTop(5);
            }else{
                //尾部插入信息
                for(var i= chatObj.total-1; i>=0; i--){
                    fillItem(rows[i]);
                    chatItem.clone().appendTo(page.blk_chatMsg);
				}
                $(page.blk_chatMsg).scrollTop($(page.blk_chatMsg)[0].scrollHeight);	//滚动到底部
            }
		}

   });

    /**
     *
     * @param string action
     *  -init: 初始化第一次读入
     *  -prev: 读取上一页信息
     *  -send: 发送一条新信息
     * @param msg
     */
	var updateMsg=function(action, msg){
		//发送到后台的聊天数据报
		var pkg={action:action,msg:msg }
        playPage.send({"chat":pkg});
	}

    /**
     * 向服务端发送新的聊天记录
     */
    this.sendMsg=function(msg){
        if("1"!=params.canChat)  {
            alert('您无权发言！您没有登录或被禁言');
            return false;
        }
        if(msg.length<1){
            return false;
        }
        updateMsg("send",msg);
        return;
    }

    //禁言事件
    $("#chatMsg").delegate(".chat-item .left-box","click",function (event) {
        var sender=$(this).attr("sender");
//console.log("sender=",sender);
        if(sender>0 && "1"==params.isAdmin){
//console.log("no char");
            if(window.confirm("确定禁止此账号发言吗？")){
                var pkg={action:"mute",userid:sender}
                playPage.send({"chat":pkg});
            }
        }
    });

    /* 新消息相关功能按钮 */
    $("#newMsg").on("click",function (event) {
        var obj=event.target;
        var func=$(obj).attr("func");
        switch (func){
            case "send":
                $("#chat-bottom .popup-box").hide('fast');
                var msg=$("#newMsg .input-box").html();
                //console.log(msg);
                _this.sendMsg(msg);
                $("#newMsg .input-box").html('');
                break;
            case "full":
                window.open("{$showfullurl}");
                break;
            case "emoji":
                $("#chat-bottom .popup-box").not("#emoji").hide();
                $("#emoji").toggle('normal');
                $("#newMsg .input-box").focus();

                var selection = getSelection()
                // 判断是否有最后光标对象存在
                if(typeof(lastEditRange)=='undefined'){
                    //console.log('uuu');
                    _this.setLastEditRange();
                }

                if (lastEditRange) {
                    // 存在最后光标对象，选定对象清除所有光标并添加最后光标还原之前的状态
                    selection.removeAllRanges()
                    selection.addRange(lastEditRange)
                }
                break;
            case "gift":
                $("#chat-bottom .popup-box").not("#gift-list").hide();
                $("#gift-list").toggle('gift');
                break;
        }
    });

    // 编辑框点击事件,编辑框按键弹起事件
    $("#newMsg .input-box").on("click keyup",function () {
        _this.setLastEditRange();
        $("#emoji").hide('fast');
        return;
    });

    /* 点击表情符号 */
    $("#emoji ul").on("click",function (event) {
        var obj=event.target;
        //console.log(obj);
        //console.log(event.delegateTarget);
        if(obj==event.delegateTarget){
            //$("#emoji").hide('fast');
        }else {
            var str=$(obj).html();
            var inputBox=$("#newMsg .input-box")[0];
            _this.insertText(str,inputBox);
        }
    });

	//初始化
	var init=function(){
        params.lastMsgId=0; params.firstMsgId=0;
        var pkg={channelId:params.channelId, userId:params.userId, lastMsgId:0, firstMsgId:0, isAdmin:params.isAdmin}
        playPage.setAppPara("chat",pkg);
        updateMsg("init");
        $(page.blk_chatMsg).on("scroll",scrollFunc );

    }
    //初始化完成后，开始读入初始显示的聊天消息
    init();

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