#!/usr/bin/env bash
set -euo pipefail

# Wrapper for Hostinger cron: ensures correct cwd + logs path.
ROOT_DIR="${ROOT_DIR:-/var/www/painel1}"
PHP_BIN="${PHP_BIN:-php}"
LOG_DIR="${LOG_DIR:-$ROOT_DIR/storage/logs/security}"

mkdir -p "$LOG_DIR"

cd "$ROOT_DIR"

mode="${1:-run}"
shift || true

case "$mode" in
  run)
    exec "$PHP_BIN" artisan security:access:run "$@"
    ;;
  ingest)
    exec "$PHP_BIN" artisan security:access:ingest "$@"
    ;;
  evaluate)
    exec "$PHP_BIN" artisan security:access:evaluate "$@"
    ;;
  prune)
    exec "$PHP_BIN" artisan security:access:prune "$@"
    ;;
  *)
    echo "usage: $0 {run|ingest|evaluate|prune} [artisan-args...]" >&2
    exit 2
    ;;
esac

