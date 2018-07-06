/**
 * 网页聊天对象控制类
 * 
 * 依赖：jquery, jeasyui
 */
////// webChat object //////
function shareRank(){
	//console.log("struct shareRank");
}

/**
 * 初始化
 */
shareRank.prototype.init=function(chnId){
	//console.log("shareRank.init");
	//显示排名信息
	$('#divLayer').show();
	DivLoadHtml('/player.php/HDPlayer/pluginShareRank/chnId/' + chnId, 'divLayer', '');

}

/**
 * 关闭
 */
shareRank.prototype.close=function(){
	console.log("shareRank.close");
	//显示排名信息
	$('#divLayer').hide();
	$('#divLayer').html('');
}




////// end of shareRank object //////