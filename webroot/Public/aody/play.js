var lssHandle,lssFunName,lssFunInterval,lssConf,playerScript;
var hlsPlayerLoad = false;
function aodianPlayer(conf)
{
	var conf = conf;
	//判断手机还是pc
	var Url=conf.url.substring(0,7);
	if(Url == 'http://')
	{
		var num=conf.url.length;
		var laststr=conf.url.substring(num-3,num);
	}
	else
	{   
		var mode = /^rtmp\:\/\/(.*)\/([a-z\_\-A-Z\-0-9]*)(\?k\=([a-z0-9]*)\&t\=\d{10,11})?\/([a-z\_\-A-Z\-0-9]*)(\?k\=([a-z0-9]*)\&t\=\d{10,11})?(.*)$/;

		var arr = conf.url.match(mode);
			conf.cname = arr[1];
			conf.app = arr[2];
			conf.key = '';
			conf.ck = arr[3] ? arr[3] : '';
			conf.pk = arr[6] ? arr[6] : '';
			conf.stream = arr[5] + conf.pk;;
			conf.addr = 'rtmp://'+ conf.cname +'/' + conf.app + conf.ck;
        playerScript = '/Public/aody/rtmpPlayer.js';       
	}   
	
	
	lssConf = conf;
	var layoutScript = document.createElement('script');
	layoutScript.type = 'text/javascript';
	layoutScript.src = playerScript;

	document.getElementsByTagName("head")[0].appendChild(layoutScript);
	lssFunName = conf.player.name + 'Run';
	lssFunInterval = setInterval("lssFunLoad()",100);

}
function lssFunLoad()
{
	if(lssFunName && lssFunName in window)
	{
		clearInterval(lssFunInterval);
		lssHandle = eval("new "+lssFunName+"(lssConf);");
	}
}
function loadplayer(url,width,height,divid){
     var flashvars={
         width: width,
         height: height,
         url: url
     };
     var video=[url];
     var support=['iPad','iPhone','ios','android+false','msie10+false'];
     CKobject.embedHTML5(divid,'player',width,height,video,flashvars,support);
}

function startPlay()
{
  lssHandle.startPlay();
}
function stopPlayer()
{
  lssHandle.stopPlayer();
}
function setMute()
{
  lssHandle.setMute(document.getElementById("isMute").checked);
}
function setVolume(){
  lssHandle.setVolume(document.getElementById("volume").value);
}
function setFullScreenMode(stretching)
{
  lssHandle.setFullScreenMode(stretching);
}
function initConnect()
{
  lssHandle.startPlay(); 
}
function initConnectad()
{
  lssHandle.initConnectad();
}
