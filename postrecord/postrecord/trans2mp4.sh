#!/bin/bash

#命令行格式：
#trans2mp4.sh basename
#功能：将源目录中的basename.flv转换成basename.mp4并移动到目标目录中，同时提取basename.mp4的一帧为jpg
sourec_dir="/tmp/rec"
target_dir="/home/www/nodertmp/video"
ffmpeg="/opt/ffmpeg/bin/ffmpeg"
basename=$1

echo ""
echo "=====trans file:${basename}======="
echo ""
#转换成MP4
${ffmpeg} -i ${sourec_dir}/${basename}.flv -n -vcodec copy -acodec copy ${target_dir}/${basename}.mp4
#提取jpg
#${ffmpeg} -i ${target_dir}/${basename}.mp4 -n -f image2 -ss 00:00:05 -vframes 1 ${target_dir}/${basename}.jpg 
# 调用WEB接口插入录像记录，并把视频文件移动到永久目录
curl --data "data={ \"recordFile\":\"${basename}.mp4\" }" http://www.av365.cn/admin.php/streamService/addRecordByFile
#删除flv
