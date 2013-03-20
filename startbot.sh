#!/bin/sh
quit=0
while [ $quit -eq 0 ]
do
php phpircbot.php > bot.log
if [ "$?" = "3" ] 
then
git pull
else
quit=1
fi
done