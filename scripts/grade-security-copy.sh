#!/usr/bin/env bash
set -euo pipefail

APP_PRIVATE_ROOT="/var/www/painel1/storage/app/private/tenants"
APP_PRIVATE_GUEST_ROOT="/var/www/painel1/storage/app/private/tenants_guest"
SECURITY_ROOT="/var/www/painel1/security"
LOG_FILE="/var/www/painel1/storage/logs/security/grade-security-copy.log"
LOCK_FILE="/tmp/grade-security-copy.lock"

exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "$(date '+%F %T') [INFO] rotina já está em execução" >> "$LOG_FILE"
  exit 0
fi

echo "$(date '+%F %T') [INFO] início da cópia diária" >> "$LOG_FILE"

find "$APP_PRIVATE_ROOT" -mindepth 1 -maxdepth 1 -type d | while read -r TENANT_DIR; do
  TENANT_UUID="$(basename "$TENANT_DIR")"
  IMPORTS_DIR="$TENANT_DIR/imports"

  if [[ ! -d "$IMPORTS_DIR" ]]; then
    continue
  fi

  DEST_ORIGINAL="$SECURITY_ROOT/$TENANT_UUID/original"
  DEST_WORKING="$SECURITY_ROOT/$TENANT_UUID/working"

  mkdir -p "$DEST_ORIGINAL" "$DEST_WORKING"

  while IFS= read -r -d '' SRC_FILE; do
    rsync -a --ignore-existing "$SRC_FILE" "$DEST_ORIGINAL/" >> "$LOG_FILE" 2>&1 || true
  done < <(find "$IMPORTS_DIR" -maxdepth 1 -type f -name "*.csv" ! -regex '.* ([0-9]+)\.csv$' ! -regex '.*/edited_.*\.csv$' -print0)

  while IFS= read -r -d '' SRC_FILE; do
    rsync -a --backup --suffix=".prev" "$SRC_FILE" "$DEST_WORKING/" >> "$LOG_FILE" 2>&1 || true
  done < <(find "$IMPORTS_DIR" -maxdepth 1 -type f \( -regex '.* ([0-9]+)\.csv$' -o -regex '.*/edited_.*\.csv$' \) -print0)

  echo "$(date '+%F %T') [INFO] tenant $TENANT_UUID sincronizado" >> "$LOG_FILE"
done

if [[ -d "$APP_PRIVATE_GUEST_ROOT" ]]; then
  find "$APP_PRIVATE_GUEST_ROOT" -mindepth 1 -maxdepth 1 -type d | while read -r GUEST_DIR; do
    GUEST_UUID="$(basename "$GUEST_DIR")"
    IMPORTS_DIR="$GUEST_DIR/imports"

    if [[ ! -d "$IMPORTS_DIR" ]]; then
      continue
    fi

    DEST_GUEST="$SECURITY_ROOT/guest/$GUEST_UUID/imports"
    mkdir -p "$DEST_GUEST"

    while IFS= read -r -d '' SRC_FILE; do
      rsync -a --ignore-existing "$SRC_FILE" "$DEST_GUEST/" >> "$LOG_FILE" 2>&1 || true
    done < <(find "$IMPORTS_DIR" -maxdepth 1 -type f -name "*.csv" -print0)

    echo "$(date '+%F %T') [INFO] guest $GUEST_UUID sincronizado" >> "$LOG_FILE"
  done
fi

echo "$(date '+%F %T') [INFO] fim da cópia diária" >> "$LOG_FILE"
