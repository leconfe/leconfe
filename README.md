# Leconfe - Conference Management System

> This is a custom Docker build of [Leconfe](https://github.com/leconfe/leconfe) with pre-built frontend assets, ready to deploy.
[![DOI](https://zenodo.org/badge/1241972788.svg)](https://doi.org/10.5281/zenodo.20308110)
[![Docker Pulls](https://img.shields.io/docker/pulls/amirul123/leconfe)](https://hub.docker.com/r/amirul123/leconfe)
[![Docker Image Size](https://img.shields.io/docker/image-size/amirul123/leconfe/latest)](https://hub.docker.com/r/amirul123/leconfe)
[![Docker Build](https://github.com/Amirul78800/leconfe/actions/workflows/docker-build.yml/badge.svg)](https://github.com/Amirul78800/leconfe/actions)

## 🐳 Docker Image

Docker Hub: **[amirul123/leconfe](https://hub.docker.com/r/amirul123/leconfe)**

```bash
docker pull amirul123/leconfe:latest
```

## 🚀 Quick Deploy (Docker Compose)

```yaml
services:
  leconfe-db:
    image: mariadb:10.11
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: yourpassword
      MYSQL_DATABASE: leconfe
      MYSQL_USER: leconfe
      MYSQL_PASSWORD: yourpassword
    volumes:
      - leconfe_db_data:/var/lib/mysql

  leconfe:
    image: amirul123/leconfe:latest
    restart: unless-stopped
    ports:
      - "8086:8080"
    depends_on:
      - leconfe-db
    environment:
      APP_NAME: Leconfe
      APP_ENV: production
      APP_KEY: base64:your-generated-key-here
      APP_URL: http://your-server-ip:8086
      APP_INSTALLED: "false"
      DB_CONNECTION: mysql
      DB_HOST: leconfe-db
      DB_PORT: "3306"
      DB_DATABASE: leconfe
      DB_USERNAME: leconfe
      DB_PASSWORD: yourpassword
      CACHE_DRIVER: file
      QUEUE_CONNECTION: sync
      SESSION_DRIVER: file
      MAIL_MAILER: log
    volumes:
      - leconfe_storage:/var/www/html/storage

volumes:
  leconfe_db_data:
  leconfe_storage:
```

## ⚙️ Setup Steps

**1. Generate APP_KEY**
```bash
docker run --rm amirul123/leconfe:latest sh -c "cd /var/www/html && php artisan key:generate --show"
```

**2. Start containers**
```bash
docker compose up -d
```

**3. Open installation wizard**
```
http://your-server-ip:8086/installation
```

**4. Complete setup, then update environment**
```
APP_INSTALLED=true
```

**5. Restart container**
```bash
docker compose restart leconfe
```

## 📦 What's Inside

- PHP 8.1 + Nginx (Alpine)
- Pre-built frontend assets (Vite/Bun)
- Composer dependencies pre-installed
- Compatible with MySQL, MariaDB, PostgreSQL

## 🔗 Links

- Original Project: https://github.com/leconfe/leconfe
- Docker Hub: https://hub.docker.com/r/amirul123/leconfe
- Live Demo: https://conference.my-edu.my
