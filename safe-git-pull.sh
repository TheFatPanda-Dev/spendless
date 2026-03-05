#!/usr/bin/env bash
set -euo pipefail

REMOTE="${1:-origin}"
BRANCH="${2:-main}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "==> Deploy started at $(date)"
echo "==> Repo: $SCRIPT_DIR"
echo "==> Remote/branch: $REMOTE/$BRANCH"

if ! command -v git >/dev/null 2>&1; then
  echo "ERROR: git is not installed." >&2
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: php is not installed." >&2
  exit 1
fi

if [ ! -f artisan ]; then
  echo "ERROR: artisan not found. Run this script from your Laravel project root." >&2
  exit 1
fi

# Refuse to deploy if tracked files were edited on server.
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  echo "ERROR: Tracked local changes found. Commit/stash/revert before deploy." >&2
  git status --short
  exit 1
fi

echo "==> Fetching latest refs"
git fetch "$REMOTE"

echo "==> Fast-forwarding to $REMOTE/$BRANCH"
git pull --ff-only "$REMOTE" "$BRANCH"

echo "==> Installing PHP dependencies"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "==> Running database migrations"
php artisan migrate --force

echo "==> Caching application config/routes/views"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# npm install is allowed by default. Build is optional because it can use more memory.
if [ -f package.json ] && command -v npm >/dev/null 2>&1; then
  echo "==> Installing Node dependencies"
  npm ci

  if [ "${RUN_NPM_BUILD:-0}" = "1" ]; then
    echo "==> Building frontend assets"
    npm run build
  else
    echo "==> Skipping npm build (set RUN_NPM_BUILD=1 to enable)"
  fi
else
  echo "==> Skipping npm steps (no package.json or npm missing)"
fi

echo "==> Deploy completed successfully at $(date)"
