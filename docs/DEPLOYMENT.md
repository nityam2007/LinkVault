# Deployment Guide

Production deployment guide for LinkVault.

## Table of Contents

- [Overview](#overview)
- [Server Requirements](#server-requirements)
- [Deployment Methods](#deployment-methods)
  - [Traditional (VPS/Dedicated)](#traditional-vpsdedicated)
  - [Docker](#docker)
  - [Platform-as-a-Service](#platform-as-a-service)
- [SSL/HTTPS Setup](#sslhttps-setup)
- [Performance Optimization](#performance-optimization)
- [Monitoring](#monitoring)
- [Backups](#backups)
- [Updates](#updates)

---

## Overview

This guide covers deploying LinkVault to a production environment. Choose the deployment method that best fits your infrastructure.

---

## Server Requirements

### Minimum Specs

| Resource | Minimum | Recommended |
|----------|---------|-------------|
| CPU | 1 core | 2+ cores |
| RAM | 1 GB | 2+ GB |
| Storage | 10 GB | 50+ GB |
| PHP | 8.2 | 8.3 |
| MySQL | 8.0 | 8.0+ |

### Required Software

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y \
    nginx \
    php8.3-fpm \
    php8.3-mysql \
    php8.3-mbstring \
    php8.3-xml \
    php8.3-curl \
    php8.3-gd \
    php8.3-zip \
    php8.3-bcmath \
    php8.3-redis \
    mysql-server \
    redis-server \
    supervisor \
    certbot \
    python3-certbot-nginx \
    git \
    unzip
```

### Install Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Install Node.js

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

---

## Deployment Methods

### Traditional (VPS/Dedicated)

#### Step 1: Clone Repository

```bash
cd /var/www
sudo git clone https://github.com/Nityam2007/LinkVault.git linkvault
cd linkvault
```

#### Step 2: Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/linkvault
sudo chmod -R 755 /var/www/linkvault
sudo chmod -R 775 /var/www/linkvault/storage
sudo chmod -R 775 /var/www/linkvault/bootstrap/cache
```

#### Step 3: Install Dependencies

```bash
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

#### Step 4: Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:
```env
APP_NAME=LinkVault
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=linkvault
DB_USERNAME=linkvault_user
DB_PASSWORD=secure_password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
```

#### Step 5: Create Database

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE linkvault CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'linkvault_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON linkvault.* TO 'linkvault_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### Step 6: Run Migrations

```bash
php artisan migrate --force
```

#### Step 7: Optimize

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan optimize
```

#### Step 8: Configure Nginx

Create `/etc/nginx/sites-available/linkvault`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;
    root /var/www/linkvault/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;

    charset utf-8;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml application/javascript application/json;
    gzip_disable "MSIE [1-6]\.";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/linkvault /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

#### Step 9: Configure Queue Worker

Create `/etc/supervisor/conf.d/linkvault-worker.conf`:

```ini
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

Start worker:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start linkvault-worker:*
```

#### Step 10: Configure Scheduler

Add to crontab (`sudo crontab -e`):

```cron
* * * * * cd /var/www/linkvault && php artisan schedule:run >> /dev/null 2>&1
```

---

### Docker

#### Docker Compose Production

Create `docker-compose.prod.yml`:

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    restart: unless-stopped
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    volumes:
      - ./storage:/var/www/html/storage
      - ./.env:/var/www/html/.env
    depends_on:
      - db
      - redis

  nginx:
    image: nginx:alpine
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf
      - ./docker/nginx/ssl:/etc/nginx/ssl
      - ./public:/var/www/html/public
    depends_on:
      - app

  db:
    image: mysql:8.0
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql

  redis:
    image: redis:alpine
    restart: unless-stopped
    volumes:
      - redis_data:/data

  worker:
    build:
      context: .
      dockerfile: Dockerfile
    restart: unless-stopped
    command: php artisan queue:work --sleep=3 --tries=3
    depends_on:
      - db
      - redis

volumes:
  db_data:
  redis_data:
```

Deploy:
```bash
docker-compose -f docker-compose.prod.yml up -d
```

---

### Platform-as-a-Service

#### Laravel Forge

1. Connect your server to Forge
2. Create a new site pointing to `/public`
3. Add repository and deploy
4. Configure environment variables
5. Enable queue worker
6. Add SSL certificate

#### Ploi

Similar to Forge - connect server, create site, deploy.

#### DigitalOcean App Platform

1. Create new App from GitHub
2. Configure as PHP app
3. Set build command: `composer install && npm install && npm run build`
4. Set run command: `php artisan serve --host=0.0.0.0 --port=$PORT`
5. Add managed database
6. Configure environment variables

---

## SSL/HTTPS Setup

### Let's Encrypt (Certbot)

```bash
sudo certbot --nginx -d your-domain.com
```

Auto-renewal is configured automatically. Test with:
```bash
sudo certbot renew --dry-run
```

### Update Environment

```env
APP_URL=https://your-domain.com
SESSION_SECURE_COOKIE=true
```

---

## Performance Optimization

### PHP-FPM Tuning

Edit `/etc/php/8.3/fpm/pool.d/www.conf`:

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

### MySQL Tuning

Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
max_connections = 200
query_cache_type = 1
query_cache_size = 64M
```

### Redis Configuration

```bash
# /etc/redis/redis.conf
maxmemory 256mb
maxmemory-policy allkeys-lru
```

### Laravel Optimization

```bash
# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev
```

### OPcache

Enable in `/etc/php/8.3/fpm/conf.d/10-opcache.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.save_comments=1
```

---

## Monitoring

### Log Locations

| Log | Location |
|-----|----------|
| Laravel | `/var/www/linkvault/storage/logs/laravel.log` |
| Nginx Access | `/var/log/nginx/access.log` |
| Nginx Error | `/var/log/nginx/error.log` |
| PHP-FPM | `/var/log/php8.3-fpm.log` |
| MySQL | `/var/log/mysql/error.log` |
| Queue Worker | `/var/www/linkvault/storage/logs/worker.log` |

### Health Check Endpoint

Add a health check route:

```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
    ]);
});
```

### Monitoring Tools

- **Laravel Telescope** - Debug dashboard (dev only)
- **Laravel Horizon** - Redis queue monitoring
- **Uptime Robot** - External uptime monitoring
- **New Relic / Datadog** - APM

---

## Backups

### Database Backup Script

Create `/usr/local/bin/backup-linkvault.sh`:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/linkvault"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="linkvault"
DB_USER="linkvault_user"
DB_PASS="your_password"

mkdir -p $BACKUP_DIR

# Database backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Storage backup
tar -czf $BACKUP_DIR/storage_$DATE.tar.gz /var/www/linkvault/storage/app

# Keep only last 7 days
find $BACKUP_DIR -type f -mtime +7 -delete

echo "Backup completed: $DATE"
```

Make executable and schedule:
```bash
chmod +x /usr/local/bin/backup-linkvault.sh
```

Add to crontab:
```cron
0 3 * * * /usr/local/bin/backup-linkvault.sh >> /var/log/linkvault-backup.log 2>&1
```

### Offsite Backups

Use rclone to sync to cloud storage:

```bash
# Install rclone
curl https://rclone.org/install.sh | sudo bash

# Configure (e.g., for S3)
rclone config

# Sync backups
rclone sync /var/backups/linkvault remote:linkvault-backups
```

---

## Updates

### Update Process

```bash
cd /var/www/linkvault

# Enable maintenance mode
php artisan down

# Pull latest changes
git pull origin main

# Install dependencies
composer install --optimize-autoloader --no-dev
npm install
npm run build

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue workers
sudo supervisorctl restart linkvault-worker:*

# Disable maintenance mode
php artisan up
```

### Zero-Downtime Deployments

For zero-downtime, consider:
- **Envoyer** - Laravel deployment tool
- **Deployer** - PHP deployment tool
- **GitHub Actions** - CI/CD pipelines

---

## Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] Strong `APP_KEY` generated
- [ ] HTTPS enabled with valid certificate
- [ ] Database credentials secure
- [ ] SSH key authentication (disable password)
- [ ] Firewall configured (ufw/iptables)
- [ ] Fail2ban installed
- [ ] Regular security updates
- [ ] File permissions correct (755/775)
- [ ] `.env` not accessible via web

---

[← Contributing](CONTRIBUTING.md) | [Back to README →](../README.md)
