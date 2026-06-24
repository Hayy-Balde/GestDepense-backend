#!/usr/bin/env bash
set -e

# Si DATABASE_URL est fourni par Render, Laravel l'utilise automatiquement
if [ -n "$DATABASE_URL" ]; then
    export DB_CONNECTION=pgsql
fi

# Génération de la clé d'application si elle est absente
if [ -z "$APP_KEY" ]; then
    APP_KEY=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")
    export APP_KEY
fi

# Mise en cache de la configuration, des routes et des vues
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Attente active de la base de données via les outils natifs de Laravel
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

# Démarrage de Nginx en arrière-plan
nginx -g 'daemon off;' &
NGINX_PID=$!

# Démarrage de PHP-FPM au premier plan
php-fpm -F -R
