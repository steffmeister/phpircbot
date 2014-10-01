#!/bin/sh
# this will launch the bot
# if the exit code of the bot is 3, we will pull the code from github
# and restart again

# check if php command exists
command -v php >/dev/null && continue || { echo "php-cli not found! Please install!"; exit 5; }

# main loop
quit=0
while [ $quit -eq 0 ]
do
	php phpircbot.php > bot.log
	returncode=$?
	if [ $returncode = "3" ] 
	then
		git pull
	else
		quit=1
	fi
done

echo "Exiting, code: $returncode"
exit $returncode
