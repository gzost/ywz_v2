#!/bin/bash
# backup mysql db
echo ========== begin `date`  ======
arch='zip -Peis**9 '   
dumpDB='mysqldump -uroot -pywz_1511 --default-character-set=utf8 --opt --extended-insert=false --triggers -R --hex-blob -x '

#rem 备份基础路径
backPath=/home/www/dbbackup
echo "Backup path: ${backPath}"
today=`date +%Y%m%d`
#echo ${today}
subdir=`date +%d`
backPath2=/mnt/ywz_data/dbbackup/${subdir}/
#echo ${backPath2}
#rem 最新的备份放在基础路径内以当前日期命名的子目录这样以一个月为循环覆盖数据
cd $backPath

if [ ! -d "${subdir}" ]; then
	mkdir "${subdir}"
fi
cd ${subdir}
#根据实际需要填写数据库名
dblist='ywz ywz2'
for db in ${dblist} ; do
	echo "Backup db: ${db}"
	${dumpDB} ${db} >${db}.sql
	${arch} ${db}.sql.zip ${db}.sql
	echo "Copying to ${backPath2}"
	cp ${db}.sql.zip ${backPath2}
	rm ${db}.sql -f
done

