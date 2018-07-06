php='/usr/bin/php'
workpath='/var/www/ywz/webapp'
logpath='/var/www/ywz/log'
account='account/system/password/admin@135'

cd $workpath
$php $workpath/cmd.php CheckAlive/checkPerMinute/$account >> $logpath/checkAlive.log
