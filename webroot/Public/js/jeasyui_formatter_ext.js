/**
 * jeasyui databox扩充
 */

/**
 * 中国习惯日期格式formatter
 */
function cn_formatter(date){
	var y = date.getFullYear();
    var m = date.getMonth()+1;
    var d = date.getDate();
    return y+'-'+(m<10?('0'+m):m)+'-'+(d<10?('0'+d):d);
}
function cn_parser(s){
	 if (!s) return new Date();
     var ss = (s.split('-'));
     var y = parseInt(ss[0],10);
     var m = parseInt(ss[1],10);
     var d = parseInt(ss[2],10);
     if (!isNaN(y) && !isNaN(m) && !isNaN(d)){
         return new Date(y,m-1,d);
     } else {
         return new Date();
     }
}

/**
 * 
 * 分页控件中文习惯格式
 * 
 * 用法：
 * 	cn_Pagination(分页控件对象);
 * 	cn_Pagination($("#dg").edatagrid('getPager'));
 */
function cn_Pagination(obj){
	obj.pagination({
		displayMsg:"正在显示 {from} 到 {to} 行，共 {total} 行",
		beforePageText:'第', afterPageText: '页，共 {pages} 页',
		showRefresh:false
	});
}

/**
 * 提交ajax后台查询并更新datagrid数据
 * @param json para	参数，有关属性：form-表单的DOM id名，dg-datagrid的dom id名，url-后台处理数据的URL地址
 * @return 后台返回符合datagrid数据格式的数据对象，并提交给datagrid
 */
function datagridQuery(para){
	var cond=$("#"+para.form).serialize();
	//$.post("__APP__/Consump/detailGetListAjax",cond,
	$.post(para.url,cond,
		function(data){
		//console.trace(data);
			//$("#"+para.dg).datagrid({url:para.url});
			//cn_Pagination($(".easyui-datagrid").datagrid('getPager'));
			//$("#"+para.dg).datagrid('loadData',data);
			pager=$("#"+para.dg).datagrid('getPager');
			pager.pagination('select', 1);	//这里会触发datagrid请求一页的数据
		},"json");
	//alert(cond);
}

$(document).ready(function(){
	//cn_Pagination($(".easyui-datagrid").datagrid('getPager'));
});