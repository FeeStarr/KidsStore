# KidsStore Run Instructions

## Prerequisites

- PHP 8.2+
- Composer
- Node.js + npm
- MySQL (or another configured DB)

## Setup

```powershell
cd c:\Users\nsanni\Downloads\KidsStore\KidsStore\kidsstore
composer install
copy .env.example .env
php artisan key:generate
```

Update .env with your DB credentials, then run:

```powershell
php artisan migrate --force
```

## Frontend

```powershell
npm install
npm run build
```

## Run (Dev)

```powershell
php artisan serve
```

## Seed Admin Accounts (Optional)

```powershell
php artisan db:seed --class=AdminUserSeeder
```

Seeded accounts (from AdminUserSeeder):

- admin@kidsstore.com / password
- support@kidsstore.com / password
- nafiyoza@gmail.com / Admin123

## Useful Commands

```powershell
php artisan migrate:status
php artisan view:clear
php artisan config:clear
```
