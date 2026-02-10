#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/grade-app}"
BRANCH="${BRANCH:-main}"
RUN_NPM_BUILD="${RUN_NPM_BUILD:-1}"
RUN_COMPOSER_INSTALL="${RUN_COMPOSER_INSTALL:-0}"
RUN_HTTP_CHECK="${RUN_HTTP_CHECK:-1}"
HTTP_CHECK_URL="${HTTP_CHECK_URL:-https://grade.com.br}"

log() {
  echo "[deploy] $*"
}

cd "$APP_DIR"

log "updating code from origin/$BRANCH"
git pull origin "$BRANCH"

if [[ "$RUN_COMPOSER_INSTALL" == "1" ]]; then
  log "installing composer dependencies"
  composer install --no-dev --optimize-autoloader
fi

if [[ "$RUN_NPM_BUILD" == "1" ]]; then
  if command -v npm >/dev/null 2>&1; then
    log "building frontend assets"
    npm ci
    npm run build
  else
    log "npm not found, skipping frontend build"
  fi
fi

log "refreshing laravel caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

if [[ -f "$APP_DIR/public/build/manifest.json" ]]; then
  log "manifest check: ok"
else
  log "manifest check: missing at public/build/manifest.json"
  exit 1
fi

if php artisan route:list --path=register | grep -q "register"; then
  log "route check: WARNING register route still exists"
else
  log "route check: register route not found (ok)"
fi

if [[ "$RUN_HTTP_CHECK" == "1" ]] && command -v curl >/dev/null 2>&1; then
  log "http check: $HTTP_CHECK_URL"
  curl -sS -I "$HTTP_CHECK_URL" | sed -n '1p'
fi

log "done"
