$ErrorActionPreference = 'Stop'

# Make `php` work from any folder for this session.
$phpDir = 'C:\Users\nsanni\Downloads\XAMPP\php\windowsXamppPhp'
if ($env:Path -notlike "*$phpDir*") {
    $env:Path = "$phpDir;$env:Path"
}

Set-Location $PSScriptRoot

# Start MySQL via XAMPP if it's not already running.
$mysql = Get-Process -Name mysqld -ErrorAction SilentlyContinue
if (-not $mysql) {
    $xamppMysql = 'C:\Users\nsanni\Downloads\XAMPP\mysql\bin\mysqld.exe'
    if (Test-Path $xamppMysql) {
        Write-Host '> starting MySQL...' -ForegroundColor Cyan
        Start-Process -FilePath $xamppMysql -ArgumentList '--defaults-file=C:\Users\nsanni\Downloads\XAMPP\mysql\bin\my.ini' -WindowStyle Hidden
        Start-Sleep -Seconds 2
    } else {
        Write-Host '! MySQL not found — please start it from XAMPP Control Panel.' -ForegroundColor Yellow
    }
}

Write-Host '> Laravel dev server: http://127.0.0.1:8000' -ForegroundColor Green
php artisan serve
