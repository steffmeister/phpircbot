#!/bin/sh
# this will launch the bot
# if the exit code of the bot is 3, we will pull the code from github
# and restart again
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
