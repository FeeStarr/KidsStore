# Production Environment Setup

This guide covers the critical environment variables that must be configured for production deployment.

## Security-Critical Settings

### 1. Application Debug Mode
```
APP_ENV=production
APP_DEBUG=false
```
⚠️ **CRITICAL:** Never set `APP_DEBUG=true` in production. This exposes sensitive stack traces to users.

### 2. Application Key
```
APP_KEY=base64:xxxxxxxxxxxx
```
Generate a new production key:
```bash
php artisan key:generate --force
```

### 3. Application URL
```
APP_URL=https://your-domain.com
```
Must be the exact production domain (used for URL generation and redirects).

## Database Configuration

```
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_PORT=3306
DB_DATABASE=kidsstore_prod
DB_USERNAME=prod_user
DB_PASSWORD=strong-secure-password
```

**Important:**
- Use a dedicated database user with limited permissions (not root)
- Use strong passwords (minimum 16 characters)
- Enable SSL for database connections if possible
- Database name should not be guessable

## Logging

```
LOG_CHANNEL=stack
LOG_LEVEL=warning
```

For additional monitoring in production, consider:
- Send logs to a centralized service (e.g., Sentry, Datadog)
- Rotate logs daily
- Archive old logs off-server

## Session & Cache

```
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_LIFETIME=1440
CACHE_STORE=redis
CACHE_PREFIX=kidsstore:prod:
```

**For high traffic:** Use Redis for sessions instead of database:
```
SESSION_DRIVER=redis
```

## Mail Configuration

```
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="KidsStore"
```

Use an SMTP service (SendGrid, AWS SES, etc.) — never use local mail.

## Payment (OPay)

```
OPAY_ENV=production
OPAY_MERCHANT_ID=your-production-merchant-id
OPAY_SECRET_KEY=your-production-secret-key
OPAY_EXPIRE_MINUTES=40
```

**Before going live:**
1. Complete OPay sandbox testing
2. Register production merchant account
3. Get production credentials
4. Configure webhook URL: `https://your-domain.com/opay/webhook`
5. Test live transactions with small amounts

## Filesystem Storage

```
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-aws-key
AWS_SECRET_ACCESS_KEY=your-aws-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-kidsstore-bucket
AWS_URL=https://your-cdn-url
```

For cloud storage, consider S3 or similar. Local file storage is not recommended for production.

## Queue & Background Jobs

```
QUEUE_CONNECTION=redis
QUEUE_DEFAULT=default
QUEUE_RETRY_AFTER=3600
```

Start queue workers:
```bash
php artisan queue:work redis --queue=default --tries=3 --max-time=3600
```

## Admin User Setup

⚠️ **IMPORTANT:** Do NOT run the admin seeder in production.

Create admin accounts manually:
```bash
php artisan tinker
>>> User::create(['email' => 'admin@yourdomain.com', 'name' => 'Admin Name', 'password' => Hash::make('strong-unique-password'), 'role' => 'superadmin'])
```

## Verification Checklist

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] Database credentials configured and tested
- [ ] `APP_KEY` is set (not empty)
- [ ] Session encryption enabled (`SESSION_ENCRYPT=true`)
- [ ] Mail service configured
- [ ] OPay production credentials set and webhook configured
- [ ] No default admin accounts present
- [ ] File storage configured (S3, etc.)
- [ ] Queue workers configured
- [ ] Backups scheduled
- [ ] Monitoring/logging set up
- [ ] SSL certificate installed
- [ ] Firewall rules configured

## Deployment Steps

1. Set all environment variables in production `.env`
2. Run migrations (if schema changes): `php artisan migrate --force`
3. Clear caches: `php artisan config:clear && php artisan cache:clear`
4. Compile for production: `npm run build`
5. Start queue workers: `php artisan queue:work`
6. Monitor logs for errors
7. Test critical user flows before announcing to users

## Monitoring in Production

Monitor these critical areas:
- Error logs (`storage/logs/laravel.log`)
- Database performance
- Payment webhook delivery
- Queue job failures
- Session data
- Disk space

Consider setting up alerts for:
- Failed payments
- Queue backlog
- High error rates
- Database connection issues
