#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SRC_ROOT="$APP_ROOT/storage/app/private/tenants"
SRC_GUEST_ROOT="$APP_ROOT/storage/app/private/tenants_guest"
DST_ROOT="$APP_ROOT/security"
LOG_DIR="$APP_ROOT/storage/logs/security"
LOG_FILE="$LOG_DIR/grade-security-sync-missing.log"
LOCK_FILE="/tmp/grade-security-sync-missing.lock"

mkdir -p "$LOG_DIR" "$DST_ROOT"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "$(date '+%F %T') [INFO] rotina já está em execução" >> "$LOG_FILE"
  exit 0
fi

if [[ ! -d "$SRC_ROOT" && ! -d "$SRC_GUEST_ROOT" ]]; then
  echo "$(date '+%F %T') [WARN] origens não encontradas: $SRC_ROOT / $SRC_GUEST_ROOT" >> "$LOG_FILE"
  exit 0
fi

echo "$(date '+%F %T') [INFO] início sync-missing" >> "$LOG_FILE"

# Copia apenas arquivos ausentes no destino e preserva estrutura.
if [[ -d "$SRC_ROOT" ]]; then
  rsync -a --ignore-existing \
    --exclude ".DS_Store" \
    --exclude "Thumbs.db" \
    "$SRC_ROOT"/ "$DST_ROOT"/ >> "$LOG_FILE" 2>&1
fi

if [[ -d "$SRC_GUEST_ROOT" ]]; then
  mkdir -p "$DST_ROOT/guest"
  rsync -a --ignore-existing \
    --exclude ".DS_Store" \
    --exclude "Thumbs.db" \
    "$SRC_GUEST_ROOT"/ "$DST_ROOT/guest"/ >> "$LOG_FILE" 2>&1
fi

echo "$(date '+%F %T') [INFO] fim sync-missing" >> "$LOG_FILE"
