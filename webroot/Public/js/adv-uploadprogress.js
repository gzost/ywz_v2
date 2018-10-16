/*
 * 异步上传文件
 * Note:需要引用jquery.form.js
 */

$.fn.uploadprogress = function() {

	$(this).change(function(){
		if(0 == $(this).val().length)
		{
			return;
		}
		var op = '{' + $.trim($(this).attr('data-options')) + '}';
		op = $.parseJSON(op);

		if(null == op.url)
		{
			op.url = '';
		}
		if(null == op.pgWidth)
		{
			op.pgWidth = '200';
		}
		else
		{
			op.pgWidth = op.pgWidth;
		}

		var f = null;
		if('true' != $(this).attr('_init'))
		{
			$(this).wrap("<form action='" + op.url + "' method='post' enctype='multipart/form-data'></form>");
			f = $(this).parent('form');
			$(f).append('<div class="easyui-progressbar" data-options="value:0,width:' + op.pgWidth + '" style="display:none;"></div>');
			$(this).attr('_init', 'true');
		}
		else
		{
			f = $(this).parent('form');
		}

		var url = $(f).attr('action') + "/t/" + $(this).attr('name');
		$(f).attr('action', url);

		var p = $(f).children('.easyui-progressbar');
		$(p).progressbar();

		var myupload = $(this).parent();
		var bar = $(myupload).children("input");

		myupload.ajaxSubmit({
			dataType:  'json',
			beforeSend: function() {
				p.show();
				p.progressbar('setValue', 0);
			},
			uploadProgress: function(event, position, total, percentComplete) {
				p.progressbar('setValue', percentComplete);
			},
			success: function(data) {
				if('true' == data.retcode)
				{
					p.progressbar('setValue', '上传成功100');

					if(null != op.success)
					{
						eval(op.success+ "('" + data.url + "')");
					}
				}
				else
				{
					if(null != data.message)
					{
						p.progressbar('setValue', data.message);
					}
					else
					{
						p.progressbar('setValue', '上传失败!');
					}
				}
			},
			error:function(xhr){
				p.progressbar('setValue', '上传失败!');
			}
		});
	});
};

$('.adv-uploadprogress').ready(function () {
	$('.adv-uploadprogress').uploadprogress();
});
