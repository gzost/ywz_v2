var HTML5_ID_BASE=0;
function html5playerRun(conf){

	var mode = /^\d{0,6}(\%)?$/;
	var width = mode.test(conf.width) ? conf.width : '100%';
	var height = mode.test(conf.height) ? conf.height : '100%';
	HTML5_ID_BASE++;
	this.uuid  /*string*/ = 'html5Media' + HTML5_ID_BASE;
	this.hlsUrl=conf.hlsUrl;
	this.container=conf.mediaid;
	this.autostart=conf.autostart;
    
    this._pureaudio = typeof(conf.pureaudio)=='boolean'?conf.pureaudio:false;//纯音频播放

	this.volume = conf.volume ? conf.volume : 80;            //音量	
	this.adveDeAddr = conf.adveDeAddr ? conf.adveDeAddr : '';//播放前显示图片地址
	this.isdisplay = conf.controlbardisplay ? conf.controlbardisplay : 'enable';//进度条显示，取值："enable" 和 "disable"。 默认为disable
	var _this=this;
    

    if(this._pureaudio){

       if(this.isdisplay == 'disable'){
		  var html='<audio id="'+this.uuid+'" preload="auto" webkit-playsinline src="'+this.hlsUrl+'" type="application/x-mpegURL"></audio>';
		  if(this.autostart == true)
			 html='<audio id="'+this.uuid+'" autoplay webkit-playsinline src="'+this.hlsUrl+'" type="application/x-mpegURL"></audio>';
	    }
	    else if(this.isdisplay == 'enable'){
	      var html='<audio id="'+this.uuid+'" controls preload="auto" webkit-playsinline src="'+this.hlsUrl+'" type="application/x-mpegURL"></audio>';
		  if(this.autostart == true)
			 html='<audio id="'+this.uuid+'" autoplay controls webkit-playsinline src="'+this.hlsUrl+'" type="application/x-mpegURL"></audio>';
	    }
    }
    else{

	    if(this.isdisplay == 'disable'){
		  var html='<video id="'+this.uuid+'" preload="auto" width="'+width+'" height="'+height+'" poster="'+this.adveDeAddr+'" webkit-playsinline src="'+this.hlsUrl+'" type="application/x-mpegURL"></video>';
		  if(this.autostart == true)
			 html='<video id="'+this.uuid+'" autoplay preload="auto" width="'+width+'" height="'+height+'" poster="'+this.adveDeAddr+'" webkit-playsinline type="application/x-mpegURL" src="'+this.hlsUrl+'" ></video>';
	    }
	    else if(this.isdisplay == 'enable'){
	      var html='<video id="'+this.uuid+'" controls preload="auto" width="'+width+'" height="'+height+'" poster="'+this.adveDeAddr+'" webkit-playsinline src="'+this.hlsUrl+'" type="application/x-mpegURL"></video>';
		  if(this.autostart == true)
			 html='<video id="'+this.uuid+'" autoplay controls preload="auto" width="'+width+'" height="'+height+'" poster="'+this.adveDeAddr+'" webkit-playsinline type="application/x-mpegURL" src="'+this.hlsUrl+'" ></video>';
	    }
    }

    /*if(this.isdisplay == 'disable'){
	  var html='<video id="'+this.uuid+'" preload="auto" width="'+width+'" height="'+height+'" poster="'+this.adveDeAddr+'" webkit-playsinline src="'+this.hlsUrl+'" type="application/x-mpegURL"></video>';
	  if(this.autostart == true)
		 html='<video id="'+this.uuid+'" autoplay preload="auto" width="'+width+'" height="'+height+'" poster="'+this.adveDeAddr+'" webkit-playsinline type="application/x-mpegURL" src="'+this.hlsUrl+'" ></video>';
    }
    else if(this.isdisplay == 'enable'){
      var html='<video id="'+this.uuid+'" controls preload="auto" width="'+width+'" height="'+height+'" poster="'+this.adveDeAddr+'" webkit-playsinline src="'+this.hlsUrl+'" type="application/x-mpegURL"></video>';
	  if(this.autostart == true)
		 html='<video id="'+this.uuid+'" autoplay controls preload="auto" width="'+width+'" height="'+height+'" poster="'+this.adveDeAddr+'" webkit-playsinline type="application/x-mpegURL" src="'+this.hlsUrl+'" ></video>';
    }*/

	document.getElementById(conf.container).innerHTML=html;
	if(this.autostart == true){
		var playset=setInterval(function(){
			if(document.getElementById(_this.uuid)){
						clearInterval(playset);						
						document.getElementById(_this.uuid).play();
					}
		},100);
	}	
	var volumeset=setInterval(function(){
		if(document.getElementById(_this.uuid)){
					clearInterval(volumeset);
					var volume=_this.volume;
					volume=(volume/100).toFixed(1);
					volume > 1 && (volume = 1);
					volume < 0 && (volume = 0);		
					document.getElementById(_this.uuid).volume=volume;
				}
	},100);
	
	if(typeof(conf.lssCallBackFunction) == 'function'){
		conf.lssCallBackFunction();}
    
    //if(conf.onReady) conf.onReady();
//---------------------------------------------------------------------------------------    

    this.addPlayerCallback = function(events, callback){
		/*if(events == 'ready'){
            this.handle.playerloadCallback = callback;
		}else if(events == 'start'){
			this.handle.startPlayCallback = callback;
		}else if(events == 'pause'){
			this.handle.pausePlayCallback = callback;
		}else if(events == 'resume'){
			this.handle.resumePlayCallback = callback;
		}else if(events == 'stop'){
			this.handle.stopPlayCallback = callback;
		}else if(events == 'empty'){
			this.handle.emptyPlayCallback = callback;
		}else if(events == 'full'){
			this.handle.fullPlayCallback = callback;
		}else if(events == 'slider.start'){
			this.handle.SliderstartCallback = callback;
		}else if(events == 'slider.end'){
			this.handle.SliderendPlayCallback = callback;
		}else */
		if (events == 'play.stop') {
			this.playStopCallback = callback;
		}
	}

	var self = this;
	document.getElementById(this.uuid).addEventListener("ended",function(){

        if(typeof self.playStopCallback == 'function'){
		   self.playStopCallback();
		}

    }, false);

    this.changePlayer = function(url){
    	document.getElementById(this.uuid).src = url;
    }
    //开始播放
	this.startPlay = function(){
		document.getElementById(this.uuid).play();
	}

    //恢复播放
    this.resumePlay = function(){
		document.getElementById(this.uuid).play();
	}
    
    //结束播放
	this.stopPlay = function(){
    	document.getElementById(this.uuid).src = "";
    }

	// 暂停播放
	this.pausePlay = function () {
		document.getElementById(this.uuid).pause();
	}
	
    //html5video获取实时时间
    this.currenttime = function(){
    	
    	return document.getElementById(this.uuid).currentTime;
    }
 
	// 设置音量
	this.setVolume = function (volume) {
		volume=(volume/100).toFixed(1);
		volume > 1 && (volume = 1);
		volume < 0 && (volume = 0);		
		document.getElementById(this.uuid).volume=volume;
		
	}
	// 设置是否静音
	this.setMute = function (isMute) {		
		if (typeof isMute != "boolean"){return;}
		document.getElementById(this.uuid).muted=isMute;
	}
	if(conf.onReady) conf.onReady();
}