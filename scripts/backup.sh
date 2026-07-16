#!/usr/bin/env bash
# =============================================================================
# KidsStore Backup & Restore Script (Linux / Production)
#
# MODES
#   Default  : full backup  - DB dump + uploads + code archive, compressed, rclone upload
#   --db-only: DB + uploads only (faster, use for daily cron runs)
#   --restore: restore DB from a .sql or .zip backup file
#
# DAILY BACKUP (add to crontab):
#   0 2 * * * /var/www/kidsstore/scripts/backup.sh --db-only --rclone-remote GD_FeeStore >> /var/log/kidsstore-backup.log 2>&1

#
# FULL BACKUP (weekly cron):
#   0 3 * * 0 /var/www/kidsstore/scripts/backup.sh --rclone-remote GD_FeeStore >> /var/log/kidsstore-backup.log 2>&1
#
# RESTORE:
#   bash scripts/backup.sh --restore --restore-file /backups/kidsstore-data-2026-07-04.zip
#
# WHAT IS BACKED UP
#   --db-only: database dump + public/storage/ (product images)
#   full:      above + git archive of HEAD + git bundle (full repo history)
#
# NOTES
#   - DB password read from .env (DB_PASSWORD) or prompted if not found
#   - Backup files saved to BACKUP_DIR and uploaded to rclone remote
#   - rclone must be installed and configured: https://rclone.org
#   - Make executable: chmod +x scripts/backup.sh
# =============================================================================

set -euo pipefail

# ── default config (override via env vars or edit below) ────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(dirname "$SCRIPT_DIR")"
DB_NAME="${DB_NAME:-kidsstore}"
DB_USER="${DB_USER:-root}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_PASS="${DB_PASSWORD:-}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/kidsstore}"  # Outside web root (not inside /var/www)
UPLOADS_PATH="${UPLOADS_PATH:-public/storage}"
RCLONE_REMOTE=""
DB_ONLY=false
RESTORE=false
RESTORE_FILE=""
KEEP_DAYS=1  # 0 = delete local backup immediately after successful upload
GPG_RECIPIENT=""  # set to your GPG key email to encrypt backups at rest
ENCRYPT=false

# ── parse arguments ──────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case "$1" in
        --db-only)       DB_ONLY=true ;;
        --restore)       RESTORE=true ;;
        --restore-file)  RESTORE_FILE="$2"; shift ;;
        --rclone-remote) RCLONE_REMOTE="$2"; shift ;;
        --db-name)       DB_NAME="$2"; shift ;;
        --db-user)       DB_USER="$2"; shift ;;
        --db-host)       DB_HOST="$2"; shift ;;
        --backup-dir)    BACKUP_DIR="$2"; shift ;;
        --keep-days)     KEEP_DAYS="$2"; shift ;;
        --encrypt)       ENCRYPT=true ;;
        --encrypt-recipient) GPG_RECIPIENT="$2"; shift ;;
        *) echo "Unknown argument: $1"; exit 1 ;;
    esac
    shift
done

# ── read DB password from .env if not already set ────────────────────────────
if [[ -z "$DB_PASS" && -f "$REPO_ROOT/.env" ]]; then
    DB_PASS=$(grep -E '^DB_PASSWORD=' "$REPO_ROOT/.env" | cut -d '=' -f2- | tr -d '"'"'" | head -1)
fi

# ── helpers ──────────────────────────────────────────────────────────────────
log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"; }
die() { echo "[ERROR] $*" >&2; exit 1; }

require_cmd() {
    command -v "$1" &>/dev/null || die "$1 is not installed. Install it and re-run."
}

# ── ensure backup dir exists ─────────────────────────────────────────────────
mkdir -p "$BACKUP_DIR"

DT=$(date '+%Y-%m-%d-%H%M')

# ============================================================
# RESTORE MODE
# ============================================================
if [[ "$RESTORE" == "true" ]]; then
    [[ -z "$RESTORE_FILE" || ! -f "$RESTORE_FILE" ]] && die "Provide a valid --restore-file path."

    require_cmd mysql

    # Prompt for password if not set
    if [[ -z "$DB_PASS" ]]; then
        read -rsp "MySQL password for $DB_USER: " DB_PASS
        echo
    fi

    log "=== RESTORE ==="
    log "File   : $RESTORE_FILE"
    log "Target : $DB_NAME"

    SQL_FILE="$RESTORE_FILE"
    TMP_DIR=""

    # Decrypt if GPG
    if [[ "$RESTORE_FILE" == *.gpg ]]; then
        require_cmd gpg
        log "Decrypting GPG file..."
        DECRYPTED="${RESTORE_FILE%.gpg}"
        gpg --decrypt --output "$DECRYPTED" "$RESTORE_FILE"
        RESTORE_FILE="$DECRYPTED"
        SQL_FILE="$DECRYPTED"
    fi

    # Extract from zip if needed
    if [[ "$RESTORE_FILE" == *.zip ]]; then
        require_cmd unzip
        TMP_DIR=$(mktemp -d)
        log "Extracting SQL from zip..."
        unzip -q "$RESTORE_FILE" -d "$TMP_DIR"
        SQL_FILE=$(find "$TMP_DIR" -name "*.sql" | head -1)
        [[ -z "$SQL_FILE" ]] && die "No .sql file found inside $RESTORE_FILE"
        log "  SQL: $SQL_FILE"
    fi

    log "Recreating database..."
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" \
        -e "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

    log "Importing dump (may take a while for large databases)..."
    START=$SECONDS
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" \
        --max_allowed_packet=256M "$DB_NAME" < "$SQL_FILE"
    log "  Done in $((SECONDS - START))s"

    TABLE_COUNT=$(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" -N \
        -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DB_NAME';" 2>/dev/null | tr -d '[:space:]')
    log "  Tables restored: $TABLE_COUNT"

    [[ -n "$TMP_DIR" ]] && rm -rf "$TMP_DIR"

    log "Restore complete. Run: php artisan migrate"
    exit 0
fi

# ============================================================
# BACKUP MODE
# ============================================================
LABEL=$([ "$DB_ONLY" == "true" ] && echo "data" || echo "full")
ZIP_NAME="kidsstore-${LABEL}-${DT}.zip"
ZIP_PATH="$BACKUP_DIR/$ZIP_NAME"

log ""
log "=== KidsStore Backup ($LABEL) $DT ==="
log "Saving to: $BACKUP_DIR"
log ""

require_cmd mysqldump
require_cmd zip

# Prompt for password if not set
if [[ -z "$DB_PASS" ]]; then
    read -rsp "MySQL password for $DB_USER: " DB_PASS
    echo
fi

TO_ZIP=()

# Step 1 - DB dump
SQL_OUT="$BACKUP_DIR/kidsstore-sql-${DT}.sql"
log "[1] Dumping database '$DB_NAME'..."
START=$SECONDS
mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" \
    --single-transaction --quick --skip-lock-tables \
    --routines --triggers --events \
    "$DB_NAME" > "$SQL_OUT"
SIZE_MB=$(du -m "$SQL_OUT" | cut -f1)
log "  Done in $((SECONDS - START))s - ${SIZE_MB}MB"
TO_ZIP+=("$SQL_OUT")

# Step 2 - Uploads folder
UPLOADS_ABS="$REPO_ROOT/$UPLOADS_PATH"
log "[2] Including uploads..."
if [[ -d "$UPLOADS_ABS" ]]; then
    TO_ZIP+=("$UPLOADS_ABS")
    log "  $UPLOADS_ABS"
else
    log "  WARNING: Uploads folder not found at $UPLOADS_ABS - skipping."
fi

# Steps 3 and 4 - code and bundle (full mode only)
if [[ "$DB_ONLY" == "false" ]]; then
    require_cmd git

    BUNDLE_PATH="$BACKUP_DIR/kidsstore-bundle-${DT}.bundle"
    log "[3] Git bundle (full history)..."
    git -C "$REPO_ROOT" bundle create "$BUNDLE_PATH" --all
    TO_ZIP+=("$BUNDLE_PATH")

    CODE_ZIP="$BACKUP_DIR/kidsstore-code-${DT}.zip"
    log "[4] Code archive..."
    git -C "$REPO_ROOT" archive -o "$CODE_ZIP" HEAD
    TO_ZIP+=("$CODE_ZIP")
fi

# Final - compress everything
log "[compress] Creating $ZIP_PATH..."
zip -q -r "$ZIP_PATH" "${TO_ZIP[@]}"
FINAL_MB=$(du -m "$ZIP_PATH" | cut -f1)
log "  Saved: $ZIP_PATH (${FINAL_MB}MB)"

# Remove intermediate files (keep uploads folder)
for f in "${TO_ZIP[@]}"; do
    [[ "$f" != "$UPLOADS_ABS" && -f "$f" ]] && rm -f "$f"
done

# Encrypt with GPG if recipient is set
UPLOAD_FILE="$ZIP_PATH"
if [[ -n "$GPG_RECIPIENT" ]]; then
    if command -v gpg &>/dev/null; then
        log "Encrypting backup with GPG for $GPG_RECIPIENT..."
        gpg --batch --yes --encrypt --recipient "$GPG_RECIPIENT" --trust-model always --output "${ZIP_PATH}.gpg" "$ZIP_PATH"
        rm -f "$ZIP_PATH"
        UPLOAD_FILE="${ZIP_PATH}.gpg"
        log "  Encrypted: $UPLOAD_FILE"
    else
        log "WARNING: gpg not installed - skipping encryption."
    fi
elif [[ "$ENCRYPT" == "true" ]]; then
    if command -v gpg &>/dev/null; then
        log "No --encrypt-recipient provided. Using symmetric encryption..."
        read -rsp "Enter GPG encryption password: " GPG_PASS
        echo
        echo "$GPG_PASS" | gpg --batch --yes --passphrase-fd 0 --symmetric --output "${ZIP_PATH}.gpg" "$ZIP_PATH"
        rm -f "$ZIP_PATH"
        UPLOAD_FILE="${ZIP_PATH}.gpg"
        log "  Encrypted (Symmetric): $UPLOAD_FILE"
    fi
fi

# Upload via rclone
if [[ -n "$RCLONE_REMOTE" ]]; then
    if command -v rclone &>/dev/null; then
        log "Uploading to ${RCLONE_REMOTE}:backups/ ..."
        rclone copy "$UPLOAD_FILE" "${RCLONE_REMOTE}:backups/" --progress
        log "Upload complete."
    else
        log "WARNING: rclone not found - skipping upload. Install from https://rclone.org"
    fi
fi

# Clear password from environment
unset DB_PASS

# Retention: manage local backup files
if [[ -n "$RCLONE_REMOTE" ]]; then
    if [[ "$KEEP_DAYS" -eq 0 ]]; then
        rm -f "$UPLOAD_FILE"
        log "Local backup deleted after upload (KEEP_DAYS=0)."
    elif [[ "$KEEP_DAYS" -gt 0 ]]; then
        CUTOFF=$(date -d "$KEEP_DAYS days ago" '+%Y-%m-%d' 2>/dev/null || date -v-"${KEEP_DAYS}"d '+%Y-%m-%d')
        OLD_FILES=$(find "$BACKUP_DIR" \( -name 'kidsstore-*.zip' -o -name 'kidsstore-*.zip.gpg' \) | while read -r f; do
            FNAME=$(basename "$f")
            FDATE=$(echo "$FNAME" | grep -oE '\d{4}-\d{2}-\d{2}')
            [[ -n "$FDATE" && "$FDATE" < "$CUTOFF" ]] && echo "$f"
        done)
        if [[ -n "$OLD_FILES" ]]; then
            COUNT=$(echo "$OLD_FILES" | wc -l)
            log "Removing $COUNT local backup(s) older than $KEEP_DAYS days..."
            echo "$OLD_FILES" | xargs rm -f
        fi
    fi
fi

log ""
log "=== Backup complete ==="
log "Local : $ZIP_PATH"
[[ -n "$RCLONE_REMOTE" ]] && log "Remote: ${RCLONE_REMOTE}:backups/$ZIP_NAME"
log ""
log "To restore:"
log "  bash scripts/backup.sh --restore --db-name $DB_NAME --restore-file $ZIP_PATH"
log ""
