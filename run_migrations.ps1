$ErrorActionPreference = 'Stop'

# Make `php` work from any folder for this session.
$phpDir = 'C:\Users\nsanni\Downloads\XAMPP\php\windowsXamppPhp'
if ($env:Path -notlike "*$phpDir*") {
    $env:Path = "$phpDir;$env:Path"
}

Set-Location $PSScriptRoot

Write-Host "Running database migrations..." -ForegroundColor Cyan
php artisan migrate --force

Write-Host "Migration complete!" -ForegroundColor Green
