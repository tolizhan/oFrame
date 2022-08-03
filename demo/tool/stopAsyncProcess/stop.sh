#!/bin/bash
kill $(pidof php)
/etc/init.d/php7.1-fpm stop
nginx -s quit
while true
do
ProcNumber=`ps -ef |grep -w php|grep -v grep|wc -l`
if [ $ProcNumber -le 0 ];then
   echo "php is not run"
   ps -o pid|grep -v 'PID'| xargs kill
else
   echo "php is  running.."
   sleep 5
   kill $(pidof php)
fi
done