#!/usr/bin/env bash
set -euo pipefail

SECURITY_ROOT="/var/www/painel1/security"
LOG_DIR="/var/www/painel1/storage/logs/security"
LOG_FILE="$LOG_DIR/grade-security-lock.log"

mkdir -p "$LOG_DIR"

if [[ ! -d "$SECURITY_ROOT" ]]; then
  echo "$(date '+%F %T') [WARN] security root não encontrado: $SECURITY_ROOT" >> "$LOG_FILE"
  exit 0
fi

LOCKED=0
FAILED=0

while IFS= read -r -d '' FILE; do
  if chattr +i "$FILE" 2>>"$LOG_FILE"; then
    LOCKED=$((LOCKED + 1))
  else
    FAILED=$((FAILED + 1))
  fi
done < <(find "$SECURITY_ROOT" -type f -print0)

echo "$(date '+%F %T') [INFO] lock finalizado: locked=$LOCKED failed=$FAILED" >> "$LOG_FILE"

