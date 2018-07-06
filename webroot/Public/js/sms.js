/**
 * 验证短信界面控制类
 */

window.smsTimer = function ()
{
}
window.smsTimerCaller = null;

function SmsHelper()
{
	//发送频率限制在60秒内
	this.timelimit = 120;

	this.sendUrl = '';

	this.count = 120;

	this.btnId = '';

	this.txtOrigin = '';

	this.isCounting = false;

	this.Init = function(url, btnId)
	{
		this.sendUrl = url;
		this.btnId = btnId;
		window.smsTimerCaller = this;
		this.txtOrigin = $('#'+this.btnId).text();
		window.smsTimer = function()
		{
			window.smsTimerCaller.disCount();
		}
	}

	this.btnClick = function(phone)
	{
		if(0 == phone.length)
		{
			return;
		}
		//是否正在计数？
		if(this.isCounting)
		{
			//正在计数，不执行
		}
		else
		{
			this.isCounting = true;
			//发送短信
			this.SendSms(phone);
		}
	}

	SmsHelper.timeCount = function(obj)
	{
		obj.disCount();
	}

	this.disCount = function()
	{
		this.count--;
		if(0 == this.count)
		{
			//倒计完
			$('#'+this.btnId).linkbutton({ text : this.txtOrigin })
			$('#'+this.btnId).linkbutton('enable');
			this.isCounting = false;
		}
		else
		{
			this.isCounting = true;
			$('#'+this.btnId).linkbutton('disable');
			$('#'+this.btnId).linkbutton({ text : this.txtOrigin+'('+this.count+')' })
			setTimeout('window.smsTimer()', 1000);
		}
	}

	//发送短信
	this.SendSms = function (phone)
	{
		var postData={ "phone": phone};
		this.txtOrigin = $('#'+this.btnId).text();
		this.count = this.timelimit;

		$.ajax({
				url: this.sendUrl,
				type: 'post',
				data: postData,
				cache: false,
				//timeout:15000,	//超时设为15秒
				dataType: 'json',
				success:function(data){
					var command='';
					var isReject='false';
					var isFresh='false';
					try{
						if( null==data || data.result !='true')
						{
							window.smsTimerCaller.isCounting = false;
							alert(data.msg);
						}
						else
						{
							//发送成功
							window.smsTimer();
						}
					}
					catch(e){
						window.smsTimerCaller.isCounting = false;
						alert(e.message);	//
						return;
					}
				},
				complete:function(){	//无论是否成功都会调用
				},
				error:function(){
					window.smsTimerCaller.isCounting = false;
					alert('网络或服务中断！');
				}
			});
	}


}