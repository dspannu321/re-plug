# RePlug Deployment Guide

This guide shows how to deploy RePlug (plain PHP + MySQL) to a server.

## 1) Requirements

- PHP 8.1+ with extensions:
  - `pdo`
  - `pdo_mysql`
  - `fileinfo`
  - `mbstring`
- MySQL 8+ (or MariaDB equivalent)
- Web server:
  - Apache 2.4+ **or**
  - Nginx + PHP-FPM
- Linux server access (SSH) or hosting panel access

## 2) Upload project files

Copy the project to your web root, for example:

- Apache typical path: `/var/www/replug`
- Nginx typical path: `/var/www/replug`

The app entry files are in the project root (`index.php`, `login.php`, `dashboard.php`, `admin.php`).

## 3) Configure environment

Copy `.env.example` to `.env` and update values:

```bash
cp .env.example .env
```

Example `.env`:

```env
APP_ENV=production
APP_URL=https://your-domain.com

DB_HOST=127.0.0.1
DB_NAME=replug_db
DB_USER=replug_user
DB_PASS=your_strong_password
```

Notes:
- Keep `.env` private (never commit it).
- `APP_URL` should be your real public URL.

## 4) Create database and user

Login to MySQL:

```bash
mysql -u root -p
```

Create DB and app user:

```sql
CREATE DATABASE replug_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'replug_user'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON replug_db.* TO 'replug_user'@'localhost';
FLUSH PRIVILEGES;
```

## 5) Import schema

RePlug currently uses these SQL files in `database/`:

- `items_table.sql`
- `pickups_table.sql`
- `alter_users_avatar.sql` (only if avatar column is missing)

Create `users` table first:

```sql
USE replug_db;
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(45) NOT NULL,
  `email` VARCHAR(128) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','technician','driver','user') NULL DEFAULT 'user',
  `avatar` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
```

Then import project SQL files:

```bash
mysql -u replug_user -p replug_db < database/items_table.sql
mysql -u replug_user -p replug_db < database/pickups_table.sql
```

If needed (older DB without avatar column):

```bash
mysql -u replug_user -p replug_db < database/alter_users_avatar.sql
```

## 6) Seed first admin account

Generate password hash on server:

```bash
php -r "echo password_hash('ChangeMe123!', PASSWORD_DEFAULT) . PHP_EOL;"
```

Use the generated hash in SQL:

```sql
USE replug_db;
INSERT INTO users (name, email, password, role)
VALUES ('Admin', 'admin@your-domain.com', 'PASTE_HASH_HERE', 'admin');
```

## 7) File permissions

RePlug writes uploads to:

- `public/storage/uploads/avatars`
- `public/storage/uploads/items`

Create and set permissions:

```bash
mkdir -p public/storage/uploads/avatars public/storage/uploads/items
chown -R www-data:www-data public/storage/uploads
chmod -R 775 public/storage/uploads
```

Replace `www-data` with your web server user if different.

## 8) Web server config

### Apache (virtual host example)

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /var/www/replug

    <Directory /var/www/replug>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/replug_error.log
    CustomLog ${APACHE_LOG_DIR}/replug_access.log combined
</VirtualHost>
```

Enable site and reload Apache:

```bash
sudo a2ensite replug.conf
sudo systemctl reload apache2
```

### Nginx + PHP-FPM (server block example)

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/replug;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

Then reload Nginx:

```bash
sudo systemctl reload nginx
```

## 9) HTTPS (recommended)

Use Let’s Encrypt:

```bash
sudo certbot --apache -d your-domain.com
# or
sudo certbot --nginx -d your-domain.com
```

Update `.env`:

```env
APP_URL=https://your-domain.com
APP_ENV=production
```

## 10) Smoke test checklist

- Open homepage: `/`
- Register a normal user account
- Login works for:
  - normal user -> `dashboard.php`
  - admin user -> `admin.php`
- Create listing in user dashboard
- Request pickup from item modal and from My Pickups form
- Upload avatar
- Confirm uploaded images are saved under `public/storage/uploads`

## 11) Troubleshooting

- **Database connection failed**
  - Check `.env` DB values
  - Confirm MySQL user privileges
  - Verify MySQL is running
- **Upload fails**
  - Check folder ownership/permissions on `public/storage/uploads`
  - Check PHP limits (`upload_max_filesize`, `post_max_size`)
- **Admin cannot access admin page**
  - Verify `users.role = 'admin'` for that account
- **500 errors**
  - Check web server logs (`apache2` or `nginx` + `php-fpm`)

