#!/usr/bin/env bash
#
# KidsStore unified backup utility (Linux).
# Invoked by: php artisan app:backup   (see app/Console/Commands/AppBackup.php)
#
# Flags:
#   --db-name NAME          MySQL database name (fallback: DB_DATABASE from .env)
#   --db-user USER          MySQL user          (fallback: DB_USERNAME from .env)
#   --db-only               Bundle database + uploads only (skip application code)
#   --keep-days N           Delete local backups older than N days (0 = keep all)
#   --rclone-remote NAME    Upload bundle to NAME:backups/ via rclone
#   --encrypt               Symmetric-encrypt bundle (needs BACKUP_GPG_PASSPHRASE in .env)
#   --restore               Restore mode (requires --restore-file)
#   --restore-file PATH     Bundle to restore (local path, or filename on the rclone remote)
#
# Bundle layout:
#   db.sql            database dump
#   .env              application environment (APP_KEY, gateway keys)
#   storage-app/      storage/app contents (custom-orders, images, private files)
#   public-images/    public/images (legacy/product files, when present)
#   code.tgz          application code (only when NOT --db-only)
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="$APP_DIR/storage/app/backups"

DB_NAME=""
DB_USER=""
DB_ONLY=0
KEEP_DAYS=0
RCLONE_REMOTE=""
ENCRYPT=0
RESTORE=0
RESTORE_FILE=""

log()  { echo "[backup] $*"; }
die()  { echo "[backup] ERROR: $*" >&2; exit 1; }

while [[ $# -gt 0 ]]; do
    case "$1" in
        --db-name)        DB_NAME="$2"; shift 2 ;;
        --db-user)        DB_USER="$2"; shift 2 ;;
        --db-only)        DB_ONLY=1; shift ;;
        --keep-days)      KEEP_DAYS="$2"; shift 2 ;;
        --rclone-remote)  RCLONE_REMOTE="$2"; shift 2 ;;
        --encrypt)        ENCRYPT=1; shift ;;
        --restore)        RESTORE=1; shift ;;
        --restore-file)   RESTORE_FILE="$2"; shift 2 ;;
        *) die "Unknown option: $1" ;;
    esac
done

# Read a value from the app .env (quotes stripped). Empty result if missing.
env_get() {
    local key="$1" line=""
    if [[ -f "$APP_DIR/.env" ]]; then
        line="$(grep -E "^${key}=" "$APP_DIR/.env" | tail -n 1 || true)"
        line="${line#*=}"
        line="${line%\"}"; line="${line#\"}"
        line="${line%\'}"; line="${line#\'}"
    fi
    printf '%s' "$line"
}

load_credentials() {
    DB_HOST="${DB_HOST:-$(env_get DB_HOST)}"
    DB_PORT="${DB_PORT:-$(env_get DB_PORT)}"
    DB_PASSWORD="${DB_PASSWORD:-$(env_get DB_PASSWORD)}"
    DB_NAME="${DB_NAME:-$(env_get DB_DATABASE)}"
    DB_USER="${DB_USER:-$(env_get DB_USERNAME)}"
    DB_HOST="${DB_HOST:-127.0.0.1}"
    DB_PORT="${DB_PORT:-3306}"
    [[ -n "$DB_NAME" ]] || die "No database name (pass --db-name or set DB_DATABASE in .env)"
    [[ -n "$DB_USER" ]] || die "No database user (pass --db-user or set DB_USERNAME in .env)"
    export MYSQL_PWD="$DB_PASSWORD"
}

find_rclone() {
    if command -v rclone >/dev/null 2>&1; then printf 'rclone'; return; fi
    if [[ -x "$HOME/bin/rclone" ]]; then printf '%s' "$HOME/bin/rclone"; return; fi
    die "rclone not found in PATH or ~/bin"
}

rclone_copyto() { # src dest
    local bin; bin="$(find_rclone)"
    "$bin" copyto "$1" "$2" --retries 3
}

STAGE=""
cleanup() { [[ -n "$STAGE" && -d "$STAGE" ]] && rm -rf "$STAGE"; return 0; }
trap cleanup EXIT

# ----------------------------------------------------------------- restore --
if [[ "$RESTORE" -eq 1 ]]; then
    [[ -n "$RESTORE_FILE" ]] || die "--restore-file is required with --restore"
    load_credentials
    command -v mysql >/dev/null 2>&1 || die "mysql client not found"

    STAGE="$(mktemp -d)"
    src="$RESTORE_FILE"

    if [[ ! -f "$src" ]]; then
        [[ -n "$RCLONE_REMOTE" ]] || die "Restore file not found locally and no --rclone-remote given: $src"
        log "Downloading $(basename "$src") from ${RCLONE_REMOTE}:backups/ ..."
        rclone_copyto "${RCLONE_REMOTE}:backups/$(basename "$src")" "$STAGE/$(basename "$src")"
        src="$STAGE/$(basename "$src")"
    fi
    [[ -f "$src" ]] || die "Restore file not found: $RESTORE_FILE"

    bundle_dir="$STAGE/bundle"
    mkdir -p "$bundle_dir"
    case "$src" in
        *.gpg) log "Decrypting bundle..."; gpg --batch --decrypt "$src" > "$STAGE/plain.tar.gz"; tar -xzf "$STAGE/plain.tar.gz" -C "$bundle_dir" ;;
        *.tar.gz|*.tgz) tar -xzf "$src" -C "$bundle_dir" ;;
        *) die "Unsupported restore file type: $src" ;;
    esac

    if [[ -f "$bundle_dir/db.sql" ]]; then
        log "Importing database into ${DB_HOST}:${DB_PORT}/${DB_NAME} ..."
        mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" < "$bundle_dir/db.sql"
        log "Database imported."
    fi

    if [[ -d "$bundle_dir/storage-app" ]]; then
        log "Restoring uploads (storage/app) ..."
        mkdir -p "$APP_DIR/storage/app"
        cp -a "$bundle_dir/storage-app/." "$APP_DIR/storage/app/"
    fi

    if [[ -d "$bundle_dir/public-images" ]]; then
        log "Restoring public/images ..."
        mkdir -p "$APP_DIR/public/images"
        cp -a "$bundle_dir/public-images/." "$APP_DIR/public/images/"
    fi

    if [[ -f "$bundle_dir/code.tgz" ]]; then
        log "Restoring application code ..."
        tar -xzf "$bundle_dir/code.tgz" -C "$APP_DIR"
    fi

    if [[ -f "$bundle_dir/.env" ]]; then
        cp -a "$bundle_dir/.env" "$APP_DIR/.env"
        log "Restored .env (existing file overwritten)."
    fi

    log "Restore complete."
    exit 0
fi

# ------------------------------------------------------------------ backup --
load_credentials
command -v mysqldump >/dev/null 2>&1 || die "mysqldump not found"
mkdir -p "$BACKUP_DIR"

STAGE="$(mktemp -d)"
STAMP="$(date +%Y%m%d-%H%M%S)"
BUNDLE="$BACKUP_DIR/kidsstore-$STAMP.tar.gz"

log "Dumping database ${DB_NAME} ..."
mysqldump \
    --single-transaction --routines --triggers --no-tablespaces \
    --default-character-set=utf8mb4 \
    -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" \
    > "$STAGE/db.sql"

log "Collecting uploads ..."
mkdir -p "$STAGE/storage-app"
if compgen -G "$APP_DIR/storage/app/*" >/dev/null; then
    tar -C "$APP_DIR/storage/app" --exclude='./backups' --exclude='./framework' -cf - . \
        | tar -C "$STAGE/storage-app" -xf -
fi

if [[ -d "$APP_DIR/public/images" ]]; then
    mkdir -p "$STAGE/public-images"
    tar -C "$APP_DIR/public" -cf - ./images | tar -C "$STAGE/public-images" -xf -
fi

cp -a "$APP_DIR/.env" "$STAGE/.env" 2>/dev/null || log "WARNING: .env not found, bundle will not include it."

if [[ "$DB_ONLY" -eq 0 ]]; then
    log "Collecting application code ..."
    tar -C "$APP_DIR" \
        --exclude='./.git' --exclude='./vendor' --exclude='./node_modules' \
        --exclude='./storage' --exclude='./.env' \
        -czf "$STAGE/code.tgz" \
        app bootstrap config database public resources routes scripts composer.json composer.lock \
        || log "WARNING: code collection failed (continuing with db + uploads only)."
fi

members=(db.sql storage-app)
if [[ -f "$STAGE/.env" ]]; then members+=(.env); fi
if [[ -d "$STAGE/public-images" ]]; then members+=(public-images); fi
if [[ -f "$STAGE/code.tgz" ]]; then members+=(code.tgz); fi

log "Creating bundle $(basename "$BUNDLE") ..."
tar -C "$STAGE" -czf "$BUNDLE" "${members[@]}"

if [[ "$ENCRYPT" -eq 1 ]]; then
    GPG_PASS="${GPG_PASS:-$(env_get BACKUP_GPG_PASSPHRASE)}"
    [[ -n "$GPG_PASS" ]] || die "BACKUP_GPG_PASSPHRASE not set in .env (required with --encrypt)"
    command -v gpg >/dev/null 2>&1 || die "gpg not found"
    log "Encrypting bundle ..."
    gpg --batch --yes --symmetric --cipher-algo AES256 --passphrase "$GPG_PASS" \
        --output "$BUNDLE.gpg" "$BUNDLE"
    rm -f "$BUNDLE"
    BUNDLE="$BUNDLE.gpg"
fi

log "Local bundle: $BUNDLE ($(du -h "$BUNDLE" | cut -f1))"

if [[ -n "$RCLONE_REMOTE" ]]; then
    log "Uploading to ${RCLONE_REMOTE}:backups/ ..."
    rclone_copyto "$BUNDLE" "${RCLONE_REMOTE}:backups/$(basename "$BUNDLE")"
    log "Uploaded: $(basename "$BUNDLE")"
fi

if [[ "$KEEP_DAYS" -gt 0 ]]; then
    removed=$(find "$BACKUP_DIR" \( -name 'kidsstore-*.tar.gz' -o -name 'kidsstore-*.tar.gz.gpg' \) -mtime +"$KEEP_DAYS" -delete -print | wc -l)
    if [[ "$removed" -gt 0 ]]; then
        log "Pruned $removed local backup(s) older than $KEEP_DAYS day(s)."
    fi
fi

log "Backup finished successfully."
exit 0
