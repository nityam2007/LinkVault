# Configuration Guide

Complete guide to configuring LinkVault for your environment.

## Table of Contents

- [Environment Variables](#environment-variables)
- [Application Settings](#application-settings)
- [Database Configuration](#database-configuration)
- [Authentication](#authentication)
- [File Storage](#file-storage)
- [Caching](#caching)
- [Queue Configuration](#queue-configuration)
- [Mail Configuration](#mail-configuration)

---

## Environment Variables

All configuration is done via the `.env` file. Copy `.env.example` to get started:

```bash
cp .env.example .env
```

### Core Application Settings

```env
# Application name (shown in browser title)
APP_NAME=LinkVault

# Environment: local, staging, production
APP_ENV=production

# Secret key (generate with: php artisan key:generate)
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# Debug mode (ALWAYS false in production!)
APP_DEBUG=false

# Application URL (your domain)
APP_URL=https://linkvault.yourdomain.com

# Timezone
APP_TIMEZONE=UTC

# Locale
APP_LOCALE=en
```

---

## Database Configuration

### MySQL / MariaDB

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=linkvault
DB_USERNAME=linkvault_user
DB_PASSWORD=your_secure_password
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci
```

### Performance Tuning

For large bookmark collections (10,000+), add to your MySQL config:

```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
max_connections = 200
```

---

## Authentication

LinkVault uses Laravel Sanctum for API authentication.

```env
# Session lifetime in minutes
SESSION_LIFETIME=120

# Session driver: file, cookie, database, redis
SESSION_DRIVER=database

# Sanctum stateful domains (comma-separated)
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,linkvault.yourdomain.com
```

### Token Settings

Configure in `config/sanctum.php`:

```php
'expiration' => null, // Tokens never expire (null) or set minutes

'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
```

---

## File Storage

### Local Storage (Default)

```env
FILESYSTEM_DISK=local
```

Archives are stored in `storage/app/archives/`.

### S3 Compatible Storage

```env
FILESYSTEM_DISK=s3

AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=linkvault-archives
AWS_URL=https://your-bucket.s3.amazonaws.com
```

### Storage Paths

| Path | Purpose |
|------|---------|
| `storage/app/archives/` | Archived page content |
| `storage/app/screenshots/` | Page screenshots |
| `storage/app/pdfs/` | PDF exports |
| `storage/app/imports/` | Temporary import files |

---

## Caching

### File Cache (Default)

```env
CACHE_DRIVER=file
```

### Redis (Recommended for Production)

```env
CACHE_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_CLIENT=phpredis
```

### Cache Keys Used

| Key Pattern | Purpose | TTL |
|-------------|---------|-----|
| `collections:tree:{user_id}` | Collection hierarchy | 1 hour |
| `bookmarks:count:{user_id}` | Bookmark counts | 5 min |
| `tags:list:{user_id}` | User's tags | 30 min |

Clear cache when needed:
```bash
php artisan cache:clear
```

---

## Queue Configuration

Queues handle background tasks like archiving and imports. **Using a proper queue is highly recommended** for archiving to avoid blocking HTTP requests.

### Sync (NOT Recommended)

```env
QUEUE_CONNECTION=sync
```

Tasks run immediately (blocking). **Warning:** This will make bookmark creation very slow (10-45+ seconds) as archiving happens synchronously.

### Database Queue (Recommended)

```env
QUEUE_CONNECTION=database
```

Run migrations and start worker:
```bash
php artisan queue:table
php artisan migrate
php artisan queue:work --queue=archives,default --tries=3 --timeout=120
```

**Important:** The queue worker must be running for archives to process in the background.

### Redis Queue (Best Performance)

```env
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

Start worker:
```bash
php artisan queue:work redis --queue=archives,default --tries=3 --timeout=120
```

**Note:** Requires Redis server to be running. If Redis is unavailable, use `database` queue instead.

### Supervisor Configuration

For production, use Supervisor to manage queue workers:

```ini
# /etc/supervisor/conf.d/linkvault-worker.conf
[program:linkvault-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/linkvault/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/linkvault/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start linkvault-worker:*
```

---

## Mail Configuration

For password resets and notifications.

### SMTP

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Mailgun

```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_SECRET=key-xxxxxxxxxxxxx
```

### Log (Development)

```env
MAIL_MAILER=log
```

Emails are written to `storage/logs/laravel.log`.

---

## Application-Specific Settings

### Archive Settings

```env
# Enable/disable archiving feature
ARCHIVE_ENABLED=true

# Maximum file size for archives (in MB)
ARCHIVE_MAX_SIZE=50

# Screenshot quality (1-100)
SCREENSHOT_QUALITY=80
```

### Import Settings

```env
# Maximum import file size (in MB)
IMPORT_MAX_SIZE=100

# Batch size for processing
IMPORT_BATCH_SIZE=100
```

### Rate Limiting

```env
# API rate limit (requests per minute)
API_RATE_LIMIT=60
```

---

## Security Settings

### HTTPS

Always use HTTPS in production:

```env
APP_URL=https://linkvault.yourdomain.com
SESSION_SECURE_COOKIE=true
```

### CORS

Configure in `config/cors.php`:

```php
'allowed_origins' => [env('APP_URL')],
'allowed_methods' => ['*'],
'allowed_headers' => ['*'],
'supports_credentials' => true,
```

### Trusted Proxies

If behind a load balancer/proxy:

```env
TRUSTED_PROXIES=*
```

---

## Production Checklist

Before going live:

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] Strong `APP_KEY` generated
- [ ] HTTPS configured
- [ ] Database credentials secure
- [ ] Cache optimized (`php artisan config:cache`)
- [ ] Routes cached (`php artisan route:cache`)
- [ ] Views cached (`php artisan view:cache`)
- [ ] Queue workers running (if using queues)
- [ ] Backups configured
- [ ] Monitoring set up

---

[← Installation](INSTALLATION.md) | [API Reference →](API.md)
