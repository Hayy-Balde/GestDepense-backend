#!/usr/bin/env bash
set -e

# Si DATABASE_URL est fourni par Render
if [ -n "$DATABASE_URL" ]; then
    export DB_CONNECTION=pgsql
fi

# IMPORTANT : On vide les caches au démarrage pour éviter les conflits
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Attente active de la base de données
echo "Waiting for database connection..."
for i in $(seq 1 30); do
    if php artisan db:monitor &>/dev/null || php artisan db:show &>/dev/null; then
        echo "Database connected."
        break
    fi
    echo "Attempt $i/30 - waiting for database..."
    sleep 2
done

# Exécution des migrations obligatoires
php artisan migrate --force

# Recréer le cache proprement si nécessaire (optionnel, vous pouvez aussi laisser clear en dev)
php artisan config:cache
php artisan route:cache

# Démarrage de Nginx en arrière-plan
nginx -g 'daemon off;' &
NGINX_PID=$!

# Démarrage de PHP-FPM au premier plan
php-fpm -F -R