/**
 * @file: miscellaneous.js
 * @brif: player项目中零散不便归类的javascript函数
 */

/*****************************************************//**
 * 当调整浏览器窗口大小后，自动调整有关显示对象的高度和宽度
 ********************************************************/
//页面装载完成立即运行的代码
$(document).ready(function(){
	$(window).resize(function(){
		resize();
	});
	resize();
});
function resize()
{
	//设置视频窗口高度
	var height = $(window).height();   // 浏览器的高度
	var top=$("#sidebar").offset().top;

	$("#sidebar").height(height-top);
	
	//top=$("#sidecontent").offset().top;
	//$("#sidecontent").height(height-top-5);
	
	//var str="h="+$("#videocontent").height()+" w="+$("#videocontent").width();
	//alert(str);
	//$("#videotitle").html(str);
}

/****************************************************//**
 * 右侧栏的活页夹显示初始化
 *******************************************************/

$(function() {
	// $( "#tabs" ).tabs({event: "mouseover"}); 鼠标经过就打开 
	$( "#tabs" ).tabs();
});

/*************************************************//**
 * 点击频道后的处理
 ***************************************************/
//指定频道对象点击的事件处理函数

$(function(){
	$(".nav li a").click( function(){getServer(this)});
});

/**
 * 点击频道的处理函数
 * 
 * 1、向服务器查询可接收此频道流的服务器列表
 * 2、把列表通过接口发给播放器程序
 * 
 * @param obj--发生消息的浏览器DOM对象
 * obj包含channelId属性，告知被选中的频道ID
 */

function getServer(obj)
{
	var para="channelId="+$(obj).attr('channelId');
	$.post("/index.php/Channel/getServerList.html",para, function(result) {
		
		$.get("http://localhost","para="+result, function(result) {},'text');
	},'text');	//把返回的Json数据按字串处理

}

