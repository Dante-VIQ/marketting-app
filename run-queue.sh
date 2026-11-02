#!/bin/sh
set -eu

SCRIPT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
cd "$SCRIPT_DIR" || exit 1

LOG="storage/logs/queue-cron.log"

# ── Find a PHP 8.4 binary ──────────────────────────────────────────────────
# cPanel/CloudLinux ships multiple PHP versions; the path name (php84) does
# NOT guarantee the binary is actually 8.4.  We probe candidates in order and
# pick the first one whose reported version starts with "8.4".
find_php84() {
    for candidate in \
        /usr/local/php84/bin/php \
        /opt/cpanel/ea-php84/root/usr/bin/php \
        /opt/alt/php84/usr/bin/php \
        /usr/bin/php84 \
        /usr/local/bin/php84 \
        $(which php84 2>/dev/null) \
        $(which php 2>/dev/null)
    do
        [ -x "$candidate" ] || continue
        ver=$("$candidate" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)
        if [ "$ver" = "8.4" ]; then
            echo "$candidate"
            return 0
        fi
    done
    return 1
}

PHP_BIN=$(find_php84)

if [ -z "$PHP_BIN" ]; then
    echo "CRON ERROR: No PHP 8.4 binary found. Queue worker NOT started. $(date '+%Y-%m-%d %H:%M:%S %Z')" >> "$LOG"
    exit 1
fi

echo "CRON: Using PHP binary: $PHP_BIN  ($(\"$PHP_BIN\" -r 'echo PHP_VERSION;'))  $(date '+%Y-%m-%d %H:%M:%S %Z')" >> "$LOG"
mkdir -p storage/logs

PHP_BIN="${PHP_BIN:-$(command -v php8.4 2>/dev/null || command -v php 2>/dev/null || true)}"
if [ -z "$PHP_BIN" ]; then
    echo "php executable not found" >&2
    exit 1
fi

# --timeout=300  : content generation + Tavily can take ~90s; give jobs up to 5 min
# --tries=1      : AI jobs are expensive — never retry automatically on failure
# --max-jobs=20  : exit cleanly after 20 jobs to avoid memory creep on shared hosting
"$PHP_BIN" artisan queue:work --stop-when-empty --tries=1 --timeout=300 --max-jobs=20 >> "$LOG" 2>&1

echo "CRON HEARTBEAT: $(date '+%Y-%m-%d %H:%M:%S %Z')" >> "$LOG"
