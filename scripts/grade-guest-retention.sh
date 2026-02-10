#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="/var/www/painel1"
LOG_DIR="$APP_ROOT/storage/logs/security"
LOG_FILE="$LOG_DIR/grade-guest-retention.log"
LOCK_FILE="/tmp/grade-guest-retention.lock"

mkdir -p "$LOG_DIR"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "$(date '+%F %T') [INFO] retenção guest já em execução" >> "$LOG_FILE"
  exit 0
fi

echo "$(date '+%F %T') [INFO] início retenção guest" >> "$LOG_FILE"

sudo -u www-data php "$APP_ROOT/artisan" guest:prune-audit --sessions-days=30 --events-days=90 >> "$LOG_FILE" 2>&1

echo "$(date '+%F %T') [INFO] fim retenção guest" >> "$LOG_FILE"

