/**
 * @file: publicFunction.js
 * @brif: 网站公共使用的函数
 */

 //设置JQuery不使用缓存
$.ajaxSetup({

    cache: false

}); 

///取子串
function csubstr(str,len)
{
	if(str.length>len)
	{
		return str.substring(0,len)+"...";
	}
	else
	{
		return str;
	}
}

///金额小数点格式化，参数单位：分
function strMoneyFmt(fee)
{
	if(1 == fee.length)
	{
		return '0.0' + fee;
	}
	else if(2 == fee.length)
	{
		return '0.' + fee;
	}
	else
	{
		return strMoneyKloFmt(fee.substring(0, fee.length-2)) + '.' + fee.substring(fee.length-2);
	}
	return '0.00';
}

///金额千分符，参数传入整数部分
function strMoneyKloFmt(yuan)
{
	if(3 < yuan.length)
	{
		return strMoneyKloFmt(yuan.substring(0, yuan.length-3)) + ',' + yuan.substring(yuan.length-3);
	}
	else
	{
		return yuan;
	}
}

//只适用于input text的控件，复制value的值
function copyToClipBoard(id, msgId)
{
	$('#'+id).select();
	document.execCommand('copy');
	if(0 < msgId && $('#' + msgId))
	{
		$('#'+msgId).text('(已复制)');
	}
}

function DivLoadHtml(urlstr, divId, formdata)
{
	if(null == formdata)
	{
		formdata = {};
	}
	$.ajax({
	  url: urlstr,
	  data: formdata,
	  success: function(data){
		$('#'+divId).html(data);
		//alert('tobe parese');
		$.parser.parse('#'+divId);
	  },
	  dataType: 'html'
	});
}
function DivReplaceHtml(urlstr, divId, formdata)
{
    if(null == formdata)
    {
        formdata = {};
    }
    $.ajax({
        url: urlstr,
        data: formdata,
        type:"POST",
        success: function(data){
            $('#'+divId).replaceWith(data);
            //alert('tobe parese');
            $.parser.parse('#'+divId);
        },
        dataType: 'html'
    });
}
function DivLoadHtmlPost(urlstr, divId, formdata)
{
    if(null == formdata)
    {
        formdata = {};
    }
    $.ajax({
        url: urlstr,
        data: formdata,
        type:"POST",
        success: function(data){
            $('#'+divId).html(data);
            //alert('tobe parese');
            $.parser.parse('#'+divId);
        },
        dataType: 'html'
    });
}

function DivLoadText(urlstr, divId, node)
{
	$.ajax({
	  url: urlstr,
	  data: {},
	  success: function(data){
		$('#'+divId).text(data[node]);
	  },
	  dataType: 'json'
	});
}

function htmlencode(s){  
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(s));
    return div.innerHTML;
}

function htmldecode(s){  
    var div = document.createElement('div');
    div.innerHTML = s;
    return div.innerText || div.textContent;
}

