#!/bin/sh

cd /home/u988184134/domains/vumbidna.com/public_html/marketting || exit 1

PHP_BIN="/opt/alt/php84/usr/bin/php"

# --timeout=300  : content generation + Tavily can take ~90s; give jobs up to 5 min
# --tries=1      : AI jobs are expensive — never retry automatically on failure
# --max-jobs=20  : exit cleanly after 20 jobs to avoid memory creep on shared hosting
"$PHP_BIN" artisan queue:work --stop-when-empty --tries=1 --timeout=300 --max-jobs=20 >> storage/logs/queue-cron.log 2>&1

echo "CRON HEARTBEAT: $(date '+%Y-%m-%d %H:%M:%S %Z')" >> storage/logs/queue-cron.log