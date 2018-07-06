#!/bin/bash

cmd="/root/postrecord.sh $1 $2 $3"
echo $cmd
ssh -p 4896 root@192.168.18.5 "$cmd"
