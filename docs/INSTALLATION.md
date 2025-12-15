# Installation Guide

This guide covers all installation methods for LinkVault.

## Table of Contents

- [Requirements](#requirements)
- [Quick Install (Development)](#quick-install-development)
- [Docker Installation](#docker-installation)
- [Manual Installation (Production)](#manual-installation-production)
- [Troubleshooting](#troubleshooting)

---

## Requirements

### Minimum Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.2+ |
| MySQL | 8.0+ |
| MariaDB | 10.6+ (alternative to MySQL) |
| Node.js | 18+ |
| Composer | 2.x |
| npm | 9+ |

### PHP Extensions Required

```
- BCMath
- Ctype
- cURL
- DOM
- Fileinfo
- JSON
- Mbstring
- OpenSSL
- PDO
- PDO_MySQL
- Tokenizer
- XML
- GD or Imagick (for image processing)
```

Check your PHP extensions:
```bash
php -m
```

### Optional (Recommended)

| Requirement | Purpose |
|-------------|---------|
| Redis 6+ | Caching, sessions, queues |
| Supervisor | Queue worker management |
| Chromium/Puppeteer | Screenshot generation |

---

## Quick Install (Development)

For local development on your machine:

### Step 1: Clone Repository

```bash
git clone https://github.com/Nityam2007/LinkVault.git
cd LinkVault
```

### Step 2: Install PHP Dependencies

```bash
composer install
```

### Step 3: Install Node Dependencies

```bash
npm install
```

### Step 4: Environment Setup

```bash
# Copy example environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### Step 5: Configure Database

Edit `.env` file with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=linkvault
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Create the database:
```bash
mysql -u root -p -e "CREATE DATABASE linkvault CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Step 6: Run Migrations

```bash
php artisan migrate
```

### Step 7: Build Frontend Assets

```bash
# Development (with hot reload)
npm run dev

# OR Production build
npm run build
```

### Step 8: Start Development Server

```bash
php artisan serve
```

Visit `http://localhost:8000` 🎉

---

## Docker Installation

### Using Docker Compose (Recommended)

#### Step 1: Clone and Configure

```bash
git clone https://github.com/Nityam2007/LinkVault.git
cd LinkVault
cp .env.example .env
```

#### Step 2: Configure Environment

Edit `.env` for Docker:

```env
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=linkvault
DB_USERNAME=linkvault
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379
```

#### Step 3: Start Containers

```bash
docker-compose up -d
```

#### Step 4: Install Dependencies

```bash
# Install PHP dependencies
docker-compose exec app composer install

# Generate key
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate
```

#### Step 5: Build Frontend

```bash
npm install
npm run build
```

#### Step 6: Access Application

Visit `http://localhost:8000`

### Docker Compose Services

The `docker-compose.yml` includes:

| Service | Port | Description |
|---------|------|-------------|
| `app` | 8000 | Laravel application |
| `db` | 3306 | MySQL/MariaDB database |
| `redis` | 6379 | Redis cache (optional) |

---

## Manual Installation (Production)

### For Apache

#### Step 1: Upload Files

Upload all files to your web server (e.g., `/var/www/linkvault`)

#### Step 2: Install Dependencies

```bash
cd /var/www/linkvault
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

#### Step 3: Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/linkvault
sudo chmod -R 755 /var/www/linkvault
sudo chmod -R 775 /var/www/linkvault/storage
sudo chmod -R 775 /var/www/linkvault/bootstrap/cache
```

#### Step 4: Apache Virtual Host

Create `/etc/apache2/sites-available/linkvault.conf`:

```apache
<VirtualHost *:80>
    ServerName linkvault.yourdomain.com
    DocumentRoot /var/www/linkvault/public

    <Directory /var/www/linkvault/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/linkvault_error.log
    CustomLog ${APACHE_LOG_DIR}/linkvault_access.log combined
</VirtualHost>
```

Enable the site:
```bash
sudo a2ensite linkvault.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### For Nginx

Create `/etc/nginx/sites-available/linkvault`:

```nginx
server {
    listen 80;
    server_name linkvault.yourdomain.com;
    root /var/www/linkvault/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

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
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/linkvault /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Step 5: Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with production settings (see [Configuration](CONFIGURATION.md)).

### Step 6: Run Migrations

```bash
php artisan migrate --force
```

### Step 7: Optimize for Production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## Troubleshooting

### Common Issues

#### 1. "Class not found" errors

```bash
composer dump-autoload
php artisan clear-compiled
```

#### 2. Permission denied errors

```bash
sudo chmod -R 775 storage bootstrap/cache
sudo chown -R $USER:www-data storage bootstrap/cache
```

#### 3. 500 Internal Server Error

Check Laravel logs:
```bash
tail -f storage/logs/laravel.log
```

#### 4. Database connection refused

- Verify database credentials in `.env`
- Ensure MySQL/MariaDB is running
- Check if database exists

#### 5. Styles not loading

```bash
npm run build
php artisan cache:clear
```

#### 6. Migration errors

```bash
php artisan migrate:fresh  # WARNING: Drops all tables!
```

### Getting Help

If you encounter issues:

1. Check the [GitHub Issues](https://github.com/Nityam2007/LinkVault/issues)
2. Review Laravel logs in `storage/logs/`
3. Ensure all requirements are met

---

[← Back to README](../README.md) | [Configuration →](CONFIGURATION.md)
