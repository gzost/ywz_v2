/**
 * 购买套餐界面控制器
 */

window.BillCaller = null;

function BillHelper()
{
	window.BillCaller = this;

	this.getInfoUrl = null;
	this.getCodeUrl = null;
	this.billCode3Recv = null;



	//提交表单，准备付款
	this.bill = function(t)
	{
		$('#form'+t).submit();
	}

	this.billCode = function(t)
	{
		$.ajax({
			url: this.getCodeUrl,
			type: 'post',
			data: $('#form'+t).serialize(),
			cache: false,
			timeout:5000,
			dataType: 'json',
			success:function(data){
				try{
						$('#billcode'+t).qrcode({
							text: data.payurl,
							width: 200,
							height: 200,
						});

						$('#billcodem'+t).show();
				}
				catch(e){
					alert(e);
				}
			},
			complete:function(){	//无论是否成功都会调用
			},
			error:function(){
				alert('网络或服务中断！');
			}
		});
	}

	this.checkCodePay = function(checkUrl, checkToken, successUrl)
	{
		$.ajax({
			url: checkUrl,
			type: 'post',
			data: 't=' + checkToken,
			cache: false,
			timeout:5000,
			dataType: 'json',
			success:function(data){
				try{
					if('true' == data.has)
					{
						//已购票成功
						location.replace(successUrl);
					}
				}
				catch(e){
					alert(e);
				}
			},
			complete:function(){	//无论是否成功都会调用
			},
			error:function(){
				alert('网络或服务中断！');
			}
		});
	}

	this.billCode2 = function(submitUrl, formId, qrcodeId, messageId)
	{
		$.ajax({
			url: submitUrl,
			type: 'post',
			data: $('#'+formId).serialize(),
			cache: false,
			timeout:5000,
			dataType: 'json',
			success:function(data){
				try{
						$('#'+qrcodeId).qrcode({
							text: data.payurl,
							width: 200,
							height: 200,
						});

						if(null != messageId)
						{
							$('#'+messageId).show();
						}
				}
				catch(e){
					alert(e);
				}
			},
			complete:function(){	//无论是否成功都会调用
			},
			error:function(){
				alert('网络或服务中断！');
			}
		});
	}

	this.billCode3 = function(submitUrl, urlPara)
	{
		$.ajax({
			url: submitUrl,
			type: 'post',
			data: urlPara,
			cache: false,
			timeout:5000,
			dataType: 'json',
			success:function(data){
				try{
					if(null != window.BillCaller.billCode3Recv)
					{
						window.BillCaller.billCode3Recv(data);
					}
				}
				catch(e){
					alert(e);
				}
			},
			complete:function(){	//无论是否成功都会调用
			},
			error:function(){
				alert('网络或服务中断！');
			}
		});
	}




	this.numchange = function(_a, t, chnId, newValue)
	{
		this.getInfo(t, chnId);
	}

	this.reduce = function(t, chnId)
	{
		var numObj = $('#' + t + 'num');
		var num = parseInt(numObj.numberbox('getValue'));
		if( 1 == num)
		{
			return;
		}
		num -= 1;
		numObj.numberbox('setValue', num);
	}

	this.add = function(t, chnId)
	{
		var numObj = $('#' + t + 'num');
		var num = parseInt(numObj.numberbox('getValue'));
		num += 1;
		numObj.numberbox('setValue', num);
		num = numObj.numberbox('getValue', num);
	}

	//由于数量变化，获取变化后的信息
	this.getInfo = function(t, chnId)
	{
		var numObj = $('#' + t + 'num');
		var num = parseInt(numObj.numberbox('getValue'));

		$.ajax({
			url: this.getInfoUrl,
			type: 'post',
			data: 't=' + t + "&chnId=" + chnId + "&num=" + num,
			cache: false,
			timeout:5000,
			dataType: 'json',
			success:function(data){
				try{
					$('#bill' + t + ' span[class=totalfee]').text(data.totalfee);
					$('#bill' + t + ' span[class=itemmeno]').text(data.meno);
					$('#form' + t + ' input[name=num]').val(num);
				}
				catch(e){
					alert(e);
				}
			},
			complete:function(){	//无论是否成功都会调用
			},
			error:function(){
				alert('网络或服务中断！');
			}
		});
	}

}