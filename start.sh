#!/usr/bin/env bash
set -e

# Parse DATABASE_URL if provided (Render PostgreSQL connection string)
if [ -n "$DATABASE_URL" ]; then
    export DB_CONNECTION=pgsql
    # Format: postgres://user:password@host:port/dbname?sslmode=require
    export DB_USERNAME=$(echo "$DATABASE_URL" | awk -F'[/:@?]' '{print $4}')
    export DB_PASSWORD=$(echo "$DATABASE_URL" | awk -F'[/:@?]' '{print $5}')
    export DB_HOST=$(echo "$DATABASE_URL" | awk -F'[/:@?]' '{print $6}')
    export DB_PORT=$(echo "$DATABASE_URL" | awk -F'[/:@?]' '{print $7}')
    export DB_DATABASE=$(echo "$DATABASE_URL" | awk -F'[/:@?]' '{print $8}')
fi

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi

# Cache config, routes, views (these don't need the database)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Wait for database to be ready
echo "Waiting for database connection..."
for i in $(seq 1 30); do
    if php -r "new PDO('pgsql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; then
        echo "Database connected."
        break
    fi
    echo "Attempt $i/30 - waiting for database..."
    sleep 2
done

# Run migrations
php artisan migrate --force

# Start Nginx in background
nginx -g 'daemon off;' &
NGINX_PID=$!

# Start PHP-FPM in foreground
php-fpm -F -R
