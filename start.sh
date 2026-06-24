#!/usr/bin/env bash
set -e

# Parse DATABASE_URL if provided (Render PostgreSQL connection string)
if [ -n "$DATABASE_URL" ]; then
    # Format: postgres://user:password@host:port/dbname
    export DB_CONNECTION=pgsql
    export DB_HOST=$(echo "$DATABASE_URL" | sed -E 's|^postgres://[^:]+:[^@]+@([^:]+).*$|\1|')
    export DB_PORT=$(echo "$DATABASE_URL" | sed -E 's|^postgres://[^:]+:[^@]+@[^:]+:([^/]+).*$|\1|')
    export DB_DATABASE=$(echo "$DATABASE_URL" | sed -E 's|^postgres://[^:]+:[^@]+@[^:]+:[^/]+/(.+)$|\1|')
    export DB_USERNAME=$(echo "$DATABASE_URL" | sed -E 's|^postgres://([^:]+):.*$|\1|')
    export DB_PASSWORD=$(echo "$DATABASE_URL" | sed -E 's|^postgres://[^:]+:([^@]+).*$|\1|')
fi

# Generate app key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:..." ]; then
    php artisan key:generate --force
fi

# Optimize Laravel
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force

# Start PHP-FPM
exec php-fpm
