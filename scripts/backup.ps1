<#
KidsStore Backup and Restore Script (PowerShell / Windows / XAMPP)

MODES
  Default  : full backup  - DB dump + uploads + code archive, compressed, rclone upload
  -DbOnly  : DB + uploads only (faster, use for daily scheduled runs)
  -Restore : restore DB from a .sql or .zip backup file

DAILY BACKUP (recommended):
  powershell -ExecutionPolicy Bypass -File .\scripts\backup.ps1 -DbOnly -DbName kidsstore -UseRclone -RcloneRemote GD_FeeStore

FULL BACKUP (weekly or before big changes):
  powershell -ExecutionPolicy Bypass -File .\scripts\backup.ps1 -DbName kidsstore -UseRclone -RcloneRemote GD_FeeStore

RESTORE:
  powershell -ExecutionPolicy Bypass -File .\scripts\backup.ps1 -Restore -DbName kidsstore -RestoreFile "C:\backups\kidsstore-data-2026-07-04.zip"

WHAT IS BACKED UP
  DB + uploads mode (-DbOnly): database dump + public/storage/ (product images)
  Full mode: above + git archive of HEAD + git bundle (full repo history)

NOTES
  Backup files are saved locally to BackupDir AND uploaded to rclone remote.
  On production Linux use the companion shell script scripts/backup.sh instead.
  DB password is always prompted securely - never stored or logged.
#>

param(
    [string]$DbName       = "kidsstore",
    [string]$DbUser       = "root",
    [string]$MysqlDump    = "C:\xampp\mysql\bin\mysqldump.exe",
    [string]$MysqlBin     = "C:\xampp\mysql\bin\mysql.exe",
    [string]$BackupDir    = "C:\xampp\kidsstore-backups",  # OUTSIDE web root - never put inside htdocs
    [string]$UploadsPath  = "public\storage",
    [switch]$DbOnly,
    [switch]$Restore,
    [string]$RestoreFile  = "",
    [switch]$UseRclone,
    [string]$RcloneRemote = "",
    [int]$KeepDays = 0   # 0 = delete local backup immediately after successful rclone upload; set >0 to keep N days locally
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Read-Password([string]$Prompt) {
    $s = Read-Host -AsSecureString $Prompt
    $b = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($s)
    $p = [Runtime.InteropServices.Marshal]::PtrToStringAuto($b)
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b)
    return $p
}

function New-ZipFrom([string[]]$Items, [string]$Dest) {
    $valid = @($Items | Where-Object { Test-Path $_ })
    if ($valid.Count -eq 0) { Write-Warning "Nothing to compress."; return }
    if (Test-Path $Dest) { Remove-Item $Dest -Force }
    Compress-Archive -Path $valid -DestinationPath $Dest -Force
    $mb = [math]::Round((Get-Item $Dest).Length / 1MB, 2)
    Write-Output "  Saved: $Dest ($mb MB)"
}

if (-not (Test-Path $BackupDir)) {
    New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
}

$repoRoot = Split-Path $PSScriptRoot -Parent

# ============================================================
# RESTORE MODE
# ============================================================
if ($Restore) {
    if (-not $RestoreFile -or -not (Test-Path $RestoreFile)) {
        Write-Error "Provide a valid -RestoreFile path."
        exit 1
    }
    $pass = Read-Password "MySQL root password"
    Write-Output ""
    Write-Output "=== RESTORE ==="
    Write-Output "File   : $RestoreFile"
    Write-Output "Target : $DbName"
    Write-Output ""
    $sqlFile = $RestoreFile
    $tmpDir  = ""
    if ($RestoreFile -match "\.zip$") {
        Write-Output "Extracting SQL from zip..."
        $tmpDir = Join-Path $env:TEMP ("kidsstore_restore_" + (Get-Date -Format "yyyyMMddHHmmss"))
        Expand-Archive -Path $RestoreFile -DestinationPath $tmpDir -Force
        $found = Get-ChildItem -Path $tmpDir -Recurse -Filter "*.sql" | Select-Object -First 1
        if (-not $found) { Write-Error "No .sql file found inside zip."; exit 1 }
        $sqlFile = $found.FullName
        Write-Output "  SQL: $sqlFile"
    }
    Write-Output "Recreating database..."
    & $MysqlBin "-u$DbUser" "--password=$pass" -e "DROP DATABASE IF EXISTS ``$DbName``; CREATE DATABASE ``$DbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    Write-Output "Importing dump..."
    $t = Get-Date
    $importCmd = ('"' + $MysqlBin + '" -u' + $DbUser + ' "--password=' + $pass + '" ' + $DbName + ' < "' + $sqlFile + '"')
    cmd.exe /c $importCmd
    Write-Output ("  Done in " + [math]::Round(((Get-Date) - $t).TotalSeconds, 1) + "s")
    $n = (& $MysqlBin "-u$DbUser" "--password=$pass" -N -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$DbName';").Trim()
    Write-Output "  Tables: $n"
    if ($tmpDir -and (Test-Path $tmpDir)) { Remove-Item $tmpDir -Recurse -Force }
    Remove-Variable pass -ErrorAction SilentlyContinue
    Write-Output ""
    Write-Output "Restore complete. Run: php artisan migrate"
    exit 0
}

# ============================================================
# BACKUP MODE
# ============================================================
$dt      = Get-Date -Format "yyyy-MM-dd-HHmm"
$label   = if ($DbOnly) { "data" } else { "full" }
$zipName = "kidsstore-$label-$dt.zip"
$zipPath = Join-Path $BackupDir $zipName
$steps   = if ($DbOnly) { 3 } else { 5 }

Write-Output ""
Write-Output "=== KidsStore Backup ($label) $dt ==="
Write-Output "Saving to: $BackupDir"
Write-Output ""

$pass    = Read-Password "MySQL root password"
$toZip   = [System.Collections.Generic.List[string]]::new()

# Step 1 - DB dump
$sqlOut = Join-Path $BackupDir "kidsstore-sql-$dt.sql"
Write-Output "[1/$steps] Dumping database '$DbName'..."
if (Test-Path $MysqlDump) {
    $t = Get-Date
    & $MysqlDump "-u$DbUser" "--password=$pass" --single-transaction --quick --skip-lock-tables --routines --triggers --events $DbName | Set-Content -Path $sqlOut -Encoding UTF8
    $mb = [math]::Round((Get-Item $sqlOut).Length / 1MB, 2)
    Write-Output ("  Done in " + [math]::Round(((Get-Date) - $t).TotalSeconds, 1) + "s - $mb MB")
    $toZip.Add($sqlOut)
} else {
    Write-Warning "mysqldump not found at $MysqlDump - skipping."
}

# Step 2 - Uploads folder
$uploadsAbs = Join-Path $repoRoot $UploadsPath
Write-Output "[2/$steps] Including uploads..."
if (Test-Path $uploadsAbs) {
    $toZip.Add($uploadsAbs)
    Write-Output "  $uploadsAbs"
} else {
    Write-Warning "Uploads folder not found at $uploadsAbs - skipping."
}

# Steps 3 and 4 - code and bundle (full mode only)
if (-not $DbOnly) {
    $bundlePath = Join-Path $BackupDir "kidsstore-bundle-$dt.bundle"
    Write-Output "[3/$steps] Git bundle (full history)..."
    Push-Location $repoRoot
    git bundle create $bundlePath --all
    Pop-Location
    $toZip.Add($bundlePath)

    $codeZip = Join-Path $BackupDir "kidsstore-code-$dt.zip"
    Write-Output "[4/$steps] Code archive..."
    Push-Location $repoRoot
    git archive -o $codeZip HEAD
    Pop-Location
    $toZip.Add($codeZip)
}

# Final - compress
Write-Output "[$steps/$steps] Compressing..."
New-ZipFrom -Items $toZip.ToArray() -Dest $zipPath

# Remove temp files (not the uploads folder itself)
foreach ($f in $toZip) {
    if ($f -ne $uploadsAbs -and (Test-Path $f)) { Remove-Item $f -Force -ErrorAction SilentlyContinue }
}

# Upload via rclone
if ($UseRclone -and $RcloneRemote -ne "") {
    if (Get-Command rclone -ErrorAction SilentlyContinue) {
        $remote = $RcloneRemote + ":backups/"
        Write-Output "Uploading to $remote ..."
        rclone copy $zipPath $remote --progress
        Write-Output "Upload complete."
    } else {
        Write-Warning "rclone not found. Install from https://rclone.org and run: rclone config"
    }
}

Remove-Variable pass -ErrorAction SilentlyContinue

# Retention: manage local backup files
if ($UseRclone -and $RcloneRemote -ne "") {
    if ($KeepDays -eq 0) {
        # Delete immediately after upload
        if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
        Write-Output "Local backup deleted after upload (KeepDays=0)."
    } elseif ($KeepDays -gt 0) {
        $cutoff = (Get-Date).AddDays(-$KeepDays)
        $old = Get-ChildItem -Path $BackupDir -Filter "kidsstore-*.zip" | Where-Object { $_.LastWriteTime -lt $cutoff }
        if ($old.Count -gt 0) {
            Write-Output "Removing $($old.Count) local backup(s) older than $KeepDays days..."
            $old | Remove-Item -Force
        }
    }
}

Write-Output ""
Write-Output "=== Backup complete ==="
Write-Output "Local : $zipPath"
$restoreCmd = "powershell -ExecutionPolicy Bypass -File .\scripts\backup.ps1 -Restore -DbName $DbName -RestoreFile $zipPath"
Write-Output "Restore command saved for reference:"
Write-Output "  $restoreCmd"
Write-Output ""







