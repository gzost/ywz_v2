/**
 * 网页聊天对象控制类
 * 
 * 依赖：jquery, jquery.form
 */
////// webChat object //////
function Upload(containId, submitUrl){
	//以下属性在执行init方法之前必须正确赋值
	div = $('#'+containId);
	html = '<div class="btn"><span>添加附件</span><input id="fileupload" type="file" name="mypic"><form action="http://192.168.1.98:92/admin.php/Channel/EditBaseSubmit" method="post" enctype="multipart/form-data"></form></div><div class="progress"><span class="bar"></span><span class="percent">0%</span ></div><div class="files"></div><div id="showimg"></div>';
	div.html(html);

	this.bar = $(containId+' .bar');
    this.percent = $(containId+' .percent');
    this.showimg = $(containId+' .showimg');
    this.progress = $(containId+' .progress');
    this.files = $(containId+' .files');
    this.btn = $(containId+' .btn span');

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

/*
    var bar = $('.bar'); 
    var percent = $('.percent'); 
    var showimg = $('#showimg'); 
    var progress = $(".progress"); 
    var files = $(".files"); 
    var btn = $(".btn span"); 
*/
    //$("#fileupload").wrap("<form id='myupload' action='action.php' method='post' enctype='multipart/form-data'></form>"); 
    $("#"+containId+" #fileupload").change(function(){ //选择文件 
		alert('onchange');
		alert($("#"+containId+" #myupload"));
		alert($("#"+containId+" #myupload").ajaxSubmit);
		
        $("#"+containId+" #myupload").ajaxSubmit({ 
            dataType:  'json', //数据格式为json 
            beforeSend: function() { //开始上传 
				alert('beforeSend');
                showimg.empty(); //清空显示的图片 
                progress.show(); //显示进度条 
                var percentVal = '0%'; //开始进度为0% 
                bar.width(percentVal); //进度条的宽度 
                percent.html(percentVal); //显示进度为0% 
                btn.html("上传中..."); //上传按钮显示上传中 
            }, 
            uploadProgress: function(event, position, total, percentComplete) { 
				alert('uploadProgress');
                var percentVal = percentComplete + '%'; //获得进度 
                bar.width(percentVal); //上传进度条宽度变宽 
                percent.html(percentVal); //显示上传进度百分比 
            }, 
            success: function(data) { //成功 
				alert('success');
                //获得后台返回的json数据，显示文件名，大小，以及删除按钮 
                files.html("<b>"+data.name+"("+data.size+"k)</b><span class='delimg' rel='"+data.pic+"'>删除</span>"); 
                //显示上传后的图片 
                var img = "http://demo.helloweba.com/upload/files/"+data.pic; 
                showimg.html("<img src='"+img+"'>"); 
                btn.html("添加附件"); //上传按钮还原 
            }, 
            error:function(xhr){ //上传失败 
				alert('error');
                btn.html("上传失败"); 
                bar.width('0'); 
                files.html(xhr.responseText); //返回失败信息 
            } 
        }); 
    }); 

}



////// end of webChat object //////