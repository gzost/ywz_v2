#!/bin/bash

# 命令行格式：
# postrecord.sh	source_dir target_dir file_name
# 功能：将source_dir的文件移动到target_dir

# 输入变量
# source_dir	录像文件源目录
# target_dir	目标目录
# file_name	录像文件名

#变量定义
base_dir="/nfs_data/ywz"
source_dir=${base_dir}$1
target_dir=${base_dir}$2
file_name=$3
ffmpeg="/usr/local/ffmpeg/ffmpeg "
file=${file_name%.*}
echo $file

if [ -z $1 ] || [ -z $2 ] || [ -z $3 ]; then
	echo "Usage: postrecord.sh	source_dir target_dir file_name"
	exit -1;
fi
#执行操作
echo ${source_dir}/${file_name} ${target_dir}/${file_name}

mv ${source_dir}/${file_name} ${target_dir}/${file_name}
if [ -e ${target_dir}/${file_name} ]; then
	${ffmpeg} -i ${target_dir}/${file_name} -y -f image2 -ss 00:00:05 -vframes 1 ${target_dir}/${file}.jpg
	echo "success"
else
	echo "failer"
fi

