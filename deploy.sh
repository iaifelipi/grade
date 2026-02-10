#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/grade-app}"
BRANCH="${BRANCH:-main}"

cd "$APP_DIR"

echo "[deploy] updating code from origin/$BRANCH"
git pull origin "$BRANCH"

echo "[deploy] refreshing laravel caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[deploy] done"
