#!/bin/bash
# Safe deployment script for KidsFlairr
# Usage: bash scripts/deploy.sh
#
# NEVER run git reset --hard or git clean on production manually.

set -e

echo "=== KidsFlairr Deployment ==="
echo ""

# ── Step 1: Ensure public_html is a symlink to public ────────────────────────
echo "[1/8] Checking public_html..."
if [ -L public_html ]; then
    echo "  public_html is already a symlink."
elif [ -d public_html ]; then
    echo "  public_html is a directory. Converting to symlink..."
    rm -rf public_html.bak
    mv public_html public_html.bak
    ln -s "$(pwd)/public" public_html
    echo "  Done. Old public_html moved to public_html.bak"
else
    ln -s "$(pwd)/public" public_html
    echo "  Created public_html symlink."
fi

# ── Step 2: Pull latest code ─────────────────────────────────────────────────
echo "[2/8] Pulling latest code..."
git pull origin main --ff-only || {
    echo "Fast-forward failed. Attempting safe merge..."
    git pull origin main --allow-unrelated-histories
}

# ── Step 3: Install dependencies ──────────────────────────────────────────────
echo "[3/8] Installing dependencies..."
php composer.phar install --no-dev --optimize-autoloader --no-interaction

# ── Step 4: Ensure directories exist ──────────────────────────────────────────
echo "[4/8] Ensuring directories exist..."
mkdir -p bootstrap/cache
mkdir -p storage/app/public
mkdir -p storage/app/private
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
chmod -R 775 storage bootstrap/cache

# ── Step 5: Fix permissions ───────────────────────────────────────────────────
echo "[5/8] Fixing permissions..."
chmod -R 775 storage bootstrap/cache

# ── Step 6: Rebuild caches ────────────────────────────────────────────────────
echo "[6/8] Rebuilding caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ── Step 7: Storage link ──────────────────────────────────────────────────────
echo "[7/8] Ensuring storage link..."
php artisan storage:link --force 2>/dev/null || true

# ── Step 8: Run migrations ────────────────────────────────────────────────────
echo "[8/8] Running migrations..."
php artisan migrate --force

# ── Cleanup old backups (keep last 5) ────────────────────────────────────────
ls -t .env.backup.* 2>/dev/null | tail -n +6 | xargs rm -f 2>/dev/null || true

echo ""
echo "=== Deployment complete ==="
