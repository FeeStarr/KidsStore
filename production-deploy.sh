#!/bin/bash
# ═══════════════════════════════════════════════════════════════════
# KidsStore Production Deployment Script
# Run AFTER deploying code to the production server
# Usage: bash production-deploy.sh
# ═══════════════════════════════════════════════════════════════════

set -e

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "  KidsStore Production Deployment Checklist"
echo "═══════════════════════════════════════════════════════════"
echo ""

# ─── 1. REMOVE DEVELOPMENT FILES ─────────────────────────────────
echo "[1/9] Removing development files..."

# Debug/utility PHP scripts
rm -f check_2fa.php check_session_driver.php check_unpaid_fees.php
rm -f clear_cache.php list_tables.php reset_db.php seed_roles.php
rm -f set_station_fee.php update_user_role.php test_email.php
rm -f test_db.php test_db_connection.php run_migration.php
rm -f backfill_pickup_fees.php ensure_test_db.php

# Windows scripts
rm -f bk_script.ps1 run_migrations.ps1 run_server.ps1 start.bat start.ps1

# Temp files and archives
rm -f tmp_*.txt app.zip *.bundle

# Test and CI config
rm -f phpunit.xml .phpunit.result.cache

# Developer config files
rm -f .styleci.yml .editorconfig .gitattributes vite.config.js
rm -f user_stories.csv

# Unnecessary documentation (keep README.md)
rm -f CHANGELOG.md README_RUN.md README_TESTS.md
rm -f PASSWORD_RESET_FEATURE.md MIGRATION_CONSOLIDATION.md
rm -f PRODUCTION_ENV_SETUP.md PRODUCTION_READINESS.md SPECIFICATION.md

# Directories that should not exist in production
rm -rf .git .vscode tests/ scripts/ system/ docs/ node_modules/

# Sensitive files
rm -f .env.testing

echo "   ✓ Development files removed"

# ─── 2. VERIFY .ENV CONFIGURATION ────────────────────────────────
echo "[2/9] Checking .env configuration..."

if [ ! -f .env ]; then
    echo "   ✗ FATAL: .env file missing!"
    exit 1
fi

if grep -q "APP_DEBUG=true" .env; then
    echo "   ✗ FATAL: APP_DEBUG is true in production!"
    exit 1
fi

if ! grep -q "APP_DEBUG=false" .env; then
    echo "   ⚠ WARNING: APP_DEBUG not explicitly set to false"
fi

if ! grep -q "SESSION_SECURE_COOKIE=true" .env; then
    echo "   ⚠ WARNING: SESSION_SECURE_COOKIE not set to true"
fi

if ! grep -q "SESSION_ENCRYPT=true" .env; then
    echo "   ⚠ WARNING: SESSION_ENCRYPT not set to true"
fi

echo "   ✓ .env configuration OK"

# ─── 3. SET OWNERSHIP ────────────────────────────────────────────
echo "[3/9] Setting ownership..."
chown -R www-data:www-data .
echo "   ✓ Ownership set to www-data:www-data"

# ─── 4. SET DIRECTORY PERMISSIONS ────────────────────────────────
echo "[4/9] Setting directory permissions..."

# All directories: 755 (read-only)
find . -type d -exec chmod 755 {} \;

# Writable directories: 775
chmod 775 bootstrap/cache
chmod 775 storage
chmod 775 storage/app
chmod 775 storage/app/custom-orders
chmod 775 storage/framework
chmod 775 storage/logs
chmod 775 public/storage 2>/dev/null || true

echo "   ✓ Directory permissions set"

# ─── 5. SET FILE PERMISSIONS ─────────────────────────────────────
echo "[5/9] Setting file permissions..."

# All files: 644 (read-only)
find . -type f -exec chmod 644 {} \;

# Sensitive files: 640 (owner + group only)
chmod 640 .env .env.example 2>/dev/null || true

# Executable
chmod 755 artisan

echo "   ✓ File permissions set"

# ─── 6. CREATE .htaccess PROTECTION ──────────────────────────────
echo "[6/9] Creating .htaccess protection..."

# Block direct access to sensitive directories
for dir in storage bootstrap/cache config database routes app; do
    if [ -d "$dir" ]; then
        cat > "$dir/.htaccess" << 'HTACCESS'
Order deny,allow
Deny from all
HTACCESS
    fi
done

# Block .git access in public root
cat > public/.htaccess << 'HTACCESS'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^\.git - [F,L]
</IfModule>
Order deny,allow
Deny from all
HTACCESS

echo "   ✓ .htaccess protection created"

# ─── 7. RUN LARAVEL OPTIMIZATION ─────────────────────────────────
echo "[7/9] Optimizing Laravel..."

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true
php artisan icons:cache 2>/dev/null || true

echo "   ✓ Laravel optimized"

# ─── 8. VERIFY KEY SETTINGS ──────────────────────────────────────
echo "[8/9] Verifying key settings..."

# Check APP_DEBUG
DEBUG_VAL=$(grep "^APP_DEBUG=" .env | cut -d '=' -f2)
if [ "$DEBUG_VAL" = "false" ]; then
    echo "   ✓ APP_DEBUG=false"
else
    echo "   ✗ APP_DEBUG=$DEBUG_VAL (should be false!)"
fi

# Check SESSION_SECURE_COOKIE
SECURE_VAL=$(grep "^SESSION_SECURE_COOKIE=" .env | cut -d '=' -f2)
if [ "$SECURE_VAL" = "true" ]; then
    echo "   ✓ SESSION_SECURE_COOKIE=true"
else
    echo "   ⚠ SESSION_SECURE_COOKIE=$SECURE_VAL (should be true)"
fi

# Check .env is not world-readable
ENV_PERMS=$(stat -c "%a" .env 2>/dev/null || stat -f "%Lp" .env 2>/dev/null)
if [ "$ENV_PERMS" = "640" ]; then
    echo "   ✓ .env permissions: 640"
else
    echo "   ⚠ .env permissions: $ENV_PERMS (should be 640)"
fi

# ─── 9. VERIFY DEPLOYMENT ───────────────────────────────────────
echo "[9/9] Final verification..."

echo ""
echo "═══════════════════════════════════════════════════════════"
echo "  Deployment Complete!"
echo "═══════════════════════════════════════════════════════════"
echo ""
echo "  Post-deployment checklist:"
echo "  ─────────────────────────"
echo "  1. Visit https://kidsflairr.com.ng — shop should load"
echo "  2. Visit https://kidsflairr.com.ng/admin — login page"
echo "  3. Check storage/logs/laravel.log for errors"
echo "  4. Test product pages and cart"
echo "  5. Test contact form"
echo "  6. Test custom frock page"
echo ""
echo "  Security verification:"
echo "  ──────────────────────"
echo "  curl -I https://kidsflairr.com.ng/.git/config"
echo "  Expected: 403 Forbidden or 404 Not Found"
echo ""
echo "  IMPORTANT: If these were ever in git, rotate NOW:"
echo "  ─────────────────────────────────────────────────"
echo "  • APP_KEY: php artisan key:generate --force"
echo "  • Database password"
echo "  • Gmail SMTP password"
echo "  • Paystack API keys"
echo ""
