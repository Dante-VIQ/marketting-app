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

"$PHP_BIN" artisan schedule:run >> storage/logs/scheduler-cron.log 2>&1

echo "CRON HEARTBEAT: $(date '+%Y-%m-%d %H:%M:%S %Z')" >> storage/logs/scheduler-cron.log