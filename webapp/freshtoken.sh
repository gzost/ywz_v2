php='/usr/bin/php'
workpath='/var/www/ywz/webroot'
logpath='/var/www/ywz/log'
cd $workpath
$php $workpath/wx.php freshToken >>$logpath/freshtoken.log
