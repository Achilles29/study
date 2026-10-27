#!/bin/sh
# Cron starts this once a minute. The outer flock prevents overlapping loops.
round=0
while [ "$round" -lt 6 ]; do
	/www/server/php/84/bin/php /www/wwwroot/study/scripts/run-notifications.php >> /var/log/projectpintar-notifications.log 2>&1
	round=$((round + 1))
	if [ "$round" -lt 6 ]; then sleep 10; fi
done
