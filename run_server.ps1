$ErrorActionPreference = 'Stop'

# Make `php` work from any folder for this session.
$phpDir = 'C:\Users\nsanni\Downloads\XAMPP\php\windowsXamppPhp'
if ($env:Path -notlike "*$phpDir*") {
    $env:Path = "$phpDir;$env:Path"
}

Set-Location $PSScriptRoot

# Check if MySQL is running
$mysql = Get-Process -Name mysqld -ErrorAction SilentlyContinue
if (-not $mysql) {
    Write-Host '> Starting MySQL...' -ForegroundColor Cyan
    $xamppMysql = 'C:\Users\nsanni\Downloads\XAMPP\mysql\bin\mysqld.exe'
    if (Test-Path $xamppMysql) {
        Start-Process -FilePath $xamppMysql -ArgumentList '--defaults-file=C:\Users\nsanni\Downloads\XAMPP\mysql\bin\my.ini' -WindowStyle Hidden
        Start-Sleep -Seconds 3
        Write-Host '✓ MySQL started' -ForegroundColor Green
    }
}

Write-Host '> Clearing Laravel caches...' -ForegroundColor Cyan
php artisan config:clear 2>&1 | Out-Null
php artisan cache:clear 2>&1 | Out-Null
Write-Host '✓ Caches cleared' -ForegroundColor Green

Write-Host ""
Write-Host "════════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host "  Laravel Development Server" -ForegroundColor Green
Write-Host "════════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host ""
Write-Host "🌐 Local URL:  http://127.0.0.1:8000" -ForegroundColor Green
Write-Host "🔐 Admin:      http://127.0.0.1:8000/admin/login" -ForegroundColor Green
Write-Host ""
Write-Host "👤 Superadmin User" -ForegroundColor Cyan
Write-Host "   Email: nafiyoza@gmail.com" -ForegroundColor White
Write-Host "   Password: Check password reset email" -ForegroundColor White
Write-Host ""
Write-Host "💌 2FA emails: Sent to nafiyoza@gmail.com via Gmail" -ForegroundColor Cyan
Write-Host ""
Write-Host "Press Ctrl+C to stop the server" -ForegroundColor Yellow
Write-Host "════════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host ""

php artisan serve
