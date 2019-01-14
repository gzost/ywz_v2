php='/usr/bin/php'
workpath='/home/www/ywz/webapp'
logpath='/home/www/ywz/logs'
account='account/system/password/admin@135'

cd $workpath
$php $workpath/cmd.php CheckAlive/checkPerMinute/$account >> $logpath/checkAlive.debug.log
