php='/usr/bin/php'
workpath='/app/varwww/ywz/webroot'
logpath='/app/varwww/ywz/log'
cd $workpath
$php $workpath/wx.php freshToken >>$logpath/freshtoken.log
