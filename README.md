# 🔗 LinkVault

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" />
  <img src="https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white" />
  <img src="https://img.shields.io/badge/TailwindCSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white" />
  <img src="https://img.shields.io/badge/MySQL-8.x-4479A1?style=for-the-badge&logo=mysql&logoColor=white" />
</p>

<p align="center">
  <strong>A powerful, self-hosted bookmark manager built with Laravel 11 + Vue.js 3</strong>
</p>

<p align="center">
  Organize, search, and archive your bookmarks with ease. Import from Linkwarden, browser exports, and more.
</p>

---

## ✨ Features

- 📁 **Hierarchical Collections** - Organize bookmarks in nested folders with color coding
- 🏷️ **Smart Tagging** - Tag bookmarks for quick filtering and discovery
- 🔍 **Full-Text Search** - Search across titles, URLs, descriptions, and notes
- 📥 **Multi-Format Import** - Import from Linkwarden JSON, browser HTML exports, CSV, and more
- 📤 **Export** - Export your data in JSON, CSV, or HTML format
- ⭐ **Favorites** - Quick access to your most important links
- 📦 **Archival** - Archive pages for offline access (screenshots, PDFs, readable content)
- 🔗 **Public Collections** - Share collections via public links
- 🌙 **Dark Mode** - Easy on the eyes with automatic theme detection
- 📱 **Responsive** - Works beautifully on desktop and mobile
- 🔒 **Self-Hosted** - Your data stays on your server
- ⚡ **High Performance** - Handles 30,000+ bookmarks with <200ms search

## 🚀 Quick Start

### Prerequisites

- PHP 8.2+
- Composer 2.x
- Node.js 18+ & npm
- MySQL 8.x / MariaDB 10.x
- Redis (optional, for caching)

### Installation

```bash
# Clone the repository
git clone https://github.com/Nityam2007/LinkVault.git
cd LinkVault

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure your database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=linkvault
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Build frontend assets
npm run build

# Start the development server
php artisan serve
```

Visit `http://localhost:8000` and create your account!

### 🐳 Docker Quick Start

```bash
# Copy environment file
cp .env.example .env

# Start containers
docker-compose up -d

# Install dependencies & migrate
docker-compose exec app composer install
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate

# Build frontend
npm install && npm run build

# Access at http://localhost:8000
```

## 📚 Documentation

Comprehensive documentation is available in the [`docs/`](docs/) directory:

| Document | Description |
|----------|-------------|
| [Installation Guide](docs/INSTALLATION.md) | Detailed setup instructions |
| [Configuration](docs/CONFIGURATION.md) | Environment variables and settings |
| [API Reference](docs/API.md) | REST API documentation |
| [Import/Export](docs/IMPORT_EXPORT.md) | Data migration guides |
| [Architecture](docs/ARCHITECTURE.md) | Code structure and design decisions |
| [Contributing](docs/CONTRIBUTING.md) | How to contribute to the project |
| [Deployment](docs/DEPLOYMENT.md) | Production deployment guides |

## 🛠️ Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | Laravel 11, PHP 8.3 |
| **Frontend** | Vue.js 3, Vite 5 |
| **Styling** | Tailwind CSS 3 |
| **Database** | MySQL 8 / MariaDB 10 |
| **Authentication** | Laravel Sanctum |
| **State Management** | Pinia |
| **Icons** | Heroicons |

## 🤝 Contributing

Contributions are welcome! Please read our [Contributing Guide](docs/CONTRIBUTING.md) for details.

## 📄 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## 🙏 Acknowledgments

- Inspired by [Linkwarden](https://github.com/linkwarden/linkwarden)
- Built with [Laravel](https://laravel.com) and [Vue.js](https://vuejs.org)

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/Nityam2007">Nityam</a>
</p>

---

## ⚠️ Disclaimer

**This is a personal hobby project and is not affiliated with, endorsed by, or related to Linkwarden or any other brand/company.**

LinkVault is a PHP/Laravel-based clone inspired by [Linkwarden](https://github.com/linkwarden/linkwarden) (which is built with Next.js/TypeScript). This project was created for learning purposes and personal use. If you're looking for the original, full-featured bookmark manager, please check out the official [Linkwarden project](https://linkwarden.app).

- ✅ This is an independent, open-source project
- ✅ Built from scratch using Laravel + Vue.js
- ✅ Not a fork or derivative of Linkwarden's codebase
- ✅ Created for educational and personal use

## Manual Installation (Apache/Nginx/LiteSpeed)

### 1. Install Dependencies

```bash
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

### 2. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your settings:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=linkwarden
DB_USERNAME=your_user
DB_PASSWORD=your_password

REDIS_HOST=127.0.0.1
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

### 3. Database Setup

```bash
php artisan migrate --force
php artisan storage:link
```

### 4. Apache Virtual Host

```apache
<VirtualHost *:80>
    ServerName linkwarden.example.com
    DocumentRoot /var/www/linkwarden/public
    
    <Directory /var/www/linkwarden/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/linkwarden-error.log
    CustomLog ${APACHE_LOG_DIR}/linkwarden-access.log combined
</VirtualHost>
```

### 5. Nginx Configuration

```nginx
server {
    listen 80;
    server_name linkwarden.example.com;
    root /var/www/linkwarden/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 6. LiteSpeed Configuration

Point document root to `/public` folder. LiteSpeed reads `.htaccess` automatically.

### 7. Queue Worker (Required for archiving)

**Systemd service** (`/etc/systemd/system/linkwarden-queue.service`):

```ini
[Unit]
Description=Linkwarden Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
WorkingDirectory=/var/www/linkwarden
ExecStart=/usr/bin/php artisan queue:work redis --sleep=3 --tries=3 --max-jobs=1000
Restart=always

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable linkwarden-queue
sudo systemctl start linkwarden-queue
```

### 8. Cron Job (Optional - for scheduled tasks)

```bash
* * * * * cd /var/www/linkwarden && php artisan schedule:run >> /dev/null 2>&1
```

## API Usage

### Authentication

```bash
# Register
curl -X POST https://your-domain.com/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"username":"john","email":"john@example.com","password":"secret123"}'

# Login
curl -X POST https://your-domain.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"secret123"}'
```

### Bookmarks

```bash
# List bookmarks
curl -X GET https://your-domain.com/api/v1/bookmarks \
  -H "Authorization: Bearer YOUR_TOKEN"

# Create bookmark
curl -X POST https://your-domain.com/api/v1/bookmarks \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"url":"https://example.com","tags":["tech","news"]}'

# Search
curl -X GET "https://your-domain.com/api/v1/bookmarks/search?q=laravel" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Import/Export

```bash
# Import HTML bookmarks
curl -X POST https://your-domain.com/api/v1/import \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@bookmarks.html"

# Export as JSON
curl -X GET "https://your-domain.com/api/v1/export?format=json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -o bookmarks.json
```

## Performance Optimization

### Cache Warming

```bash
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Database Indexes

Migrations include optimized indexes for:
- Full-text search on title, description, url, notes
- Foreign key relationships
- Composite indexes for common queries

### Redis Configuration

For large bookmark collections (30k+), tune Redis:

```conf
maxmemory 256mb
maxmemory-policy allkeys-lru
```

## Storage

Archives are stored at: `storage/app/archives/{user_id}/{bookmark_id}/`

Structure:
```
archives/
└── 1/                          # User ID
    └── 123/                    # Bookmark ID
        ├── article.html        # Cleaned article content
        ├── metadata.json       # Page metadata
        └── images/             # Downloaded images
            ├── img_abc123.jpg
            └── img_def456.png
```

## Troubleshooting

### Queue not processing

```bash
php artisan queue:restart
php artisan queue:work --verbose
```

### Search not working

```bash
# Ensure FULLTEXT indexes exist
php artisan migrate:fresh  # Warning: destroys data

# Or manually:
# ALTER TABLE bookmarks ADD FULLTEXT(title, description, url, notes);
```

### Permission issues

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

## License

MIT License
