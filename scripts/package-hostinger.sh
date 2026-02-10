#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT_DIR="${ROOT_DIR}/tmp/deploy"
STAMP="$(date +%Y%m%d-%H%M%S)"
SHORT_SHA="$(git -C "${ROOT_DIR}" rev-parse --short HEAD 2>/dev/null || echo 'nogit')"
PKG_NAME="grade-hostinger-${STAMP}-${SHORT_SHA}.tar.gz"
PKG_PATH="${OUT_DIR}/${PKG_NAME}"

WITH_VENDOR="${1:-}"
WITH_BUILD="${2:-}"

mkdir -p "${OUT_DIR}"

echo "[1/4] Preparando artefato..."
echo "Root: ${ROOT_DIR}"
echo "Output: ${PKG_PATH}"

EXCLUDES=(
  --exclude=.git
  --exclude=.env
  --exclude=.env.backup
  --exclude=.env.production
  --exclude=.env.testing
  --exclude=node_modules
  --exclude=vendor
  --exclude=tests
  --exclude=security
  --exclude=storage/app/private
  --exclude=storage/logs
  --exclude=storage/framework/cache/data
  --exclude=storage/framework/sessions
  --exclude=storage/framework/views
  --exclude=public/storage
  --exclude=public/hot
  --exclude=tmp
  --exclude='*.csv'
)

if [[ "${WITH_VENDOR}" == "--with-vendor" ]]; then
  EXCLUDES=("${EXCLUDES[@]/--exclude=vendor}")
fi

if [[ "${WITH_BUILD}" == "--with-build" ]]; then
  :
else
  EXCLUDES+=(--exclude=public/build)
fi

echo "[2/4] Gerando pacote..."
tar -czf "${PKG_PATH}" -C "${ROOT_DIR}" "${EXCLUDES[@]}" .

echo "[3/4] Verificando tamanho..."
du -h "${PKG_PATH}" | sed -n '1p'

echo "[4/4] Pronto."
echo "Arquivo: ${PKG_PATH}"
echo ""
echo "Uso:"
echo "  ./scripts/package-hostinger.sh"
echo "  ./scripts/package-hostinger.sh --with-vendor"
echo "  ./scripts/package-hostinger.sh --with-vendor --with-build"

