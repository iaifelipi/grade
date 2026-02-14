#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"

URL=""
APPLY_MIGRATIONS="0"

usage() {
  cat <<'TXT'
usage: scripts/grade-postdeploy-validate.sh [--url https://grade.com.br] [--apply-migrations]

Checks (non-destructive by default):
- PHP runtime
- vendor/autoload.php exists
- Vite manifest exists
- Storage/cache writable
- DB connectivity via Laravel
- Migrations pending (read-only)
- register route disabled
- Security commands (evaluate/prune dry-run)
- Optional HTTP checks (if --url provided)

Options:
  --url URL              Base URL for HTTP checks (e.g. https://grade.com.br)
  --apply-migrations     Runs "php artisan migrate --force" (destructive-ish; applies pending migrations)
TXT
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --url)
      URL="${2:-}"; shift 2
      ;;
    --apply-migrations)
      APPLY_MIGRATIONS="1"; shift
      ;;
    -h|--help)
      usage; exit 0
      ;;
    *)
      echo "unknown arg: $1" >&2
      usage
      exit 2
      ;;
  esac
done

cd "$ROOT_DIR"

echo "[validate] date: $(date -u +'%F %T UTC')"
if command -v git >/dev/null 2>&1; then
  if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "[validate] git: $(git rev-parse --short HEAD) branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo '-')"
    if ! git diff --quiet || ! git diff --cached --quiet; then
      echo "[validate] WARN: working tree has local changes"
    fi
  fi
fi

echo "[validate] php: $($PHP_BIN -v | head -n 1)"

echo "[validate] vendor autoload"
test -f vendor/autoload.php
echo "[validate] vendor: ok"

echo "[validate] vite manifest"
test -f public/build/manifest.json
echo "[validate] manifest: ok"

echo "[validate] writable dirs"
test -w storage
test -w storage/framework
test -w storage/logs
test -w bootstrap/cache
echo "[validate] writable: ok"

echo "[validate] artisan: $($PHP_BIN artisan --version)"

echo "[validate] DB connect"
$PHP_BIN artisan tinker --execute='DB::connection()->getPdo(); echo "DB_OK name=".DB::connection()->getDatabaseName().PHP_EOL;'

echo "[validate] migrations pending"
pending="$($PHP_BIN artisan migrate:status --no-ansi | awk '/Pending/{c++} END{print c+0}')"
echo "[validate] migrations_pending=$pending"
if [[ "$pending" != "0" ]]; then
  if [[ "$APPLY_MIGRATIONS" == "1" ]]; then
    echo "[validate] applying migrations (--apply-migrations)"
    $PHP_BIN artisan migrate --force --no-ansi
  else
    echo "[validate] ERROR: pending migrations (run with --apply-migrations or migrate manually)" >&2
    exit 10
  fi
fi

echo "[validate] register route disabled"
if $PHP_BIN artisan route:list --no-ansi | rg -q '(^|\\s)register(\\s|$)'; then
  echo "[validate] ERROR: register route still present" >&2
  $PHP_BIN artisan route:list --no-ansi | rg '(^|\\s)register(\\s|$)' || true
  exit 11
fi
echo "[validate] register route: ok"

echo "[validate] security commands"
$PHP_BIN artisan security:access:evaluate --minutes=15 --no-ansi >/dev/null
$PHP_BIN artisan security:access:prune --dry-run --no-ansi >/dev/null
echo "[validate] security: ok"

if [[ -n "$URL" ]]; then
  if command -v curl >/dev/null 2>&1; then
    echo "[validate] http: $URL"
    curl -fsSI "$URL" | head -n 5
    echo "[validate] http: $URL/up"
    curl -fsSI "$URL/up" | head -n 5
  else
    echo "[validate] WARN: curl not found, skipping http checks"
  fi
fi

echo "[validate] done"

