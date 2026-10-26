#!/bin/sh

cd /home/u988184134/domains/vumbidna.com/public_html/marketting || exit 1

PHP_BIN="/opt/alt/php84/usr/bin/php"

"$PHP_BIN" artisan queue:work --stop-when-empty --tries=3 --timeout=90 >> storage/logs/queue-cron.log 2>&1

echo "CRON HEARTBEAT: $(date '+%Y-%m-%d %H:%M:%S %Z')" >> storage/logs/queue-cron.log