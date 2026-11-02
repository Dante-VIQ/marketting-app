#!/bin/sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
cd "$SCRIPT_DIR" || exit 1

mkdir -p storage/logs

PHP_BIN="${PHP_BIN:-$(command -v php8.4 2>/dev/null || command -v php 2>/dev/null || true)}"
if [ -z "$PHP_BIN" ]; then
    echo "php executable not found" >&2
    exit 1
fi

# --timeout=300  : content generation + Tavily can take ~90s; give jobs up to 5 min
# --tries=1      : AI jobs are expensive — never retry automatically on failure
# --max-jobs=20  : exit cleanly after 20 jobs to avoid memory creep on shared hosting
"$PHP_BIN" artisan queue:work --stop-when-empty --tries=1 --timeout=300 --max-jobs=20 >> storage/logs/queue-cron.log 2>&1

echo "CRON HEARTBEAT: $(date '+%Y-%m-%d %H:%M:%S %Z')" >> storage/logs/queue-cron.log