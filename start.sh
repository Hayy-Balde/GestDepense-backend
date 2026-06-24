#!/usr/bin/env bash
set -e

# Génération de la clé d'application si absente
if [ -z "$APP_KEY" ]; then
    APP_KEY=$(php -r "echo 'base64:' . base64_encode(random_bytes(32));")
    export APP_KEY
fi

# Cache config (utilise les variables d'environnement dispo)
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Attente de la base de données
echo "Waiting for database connection..."
for i in $(seq 1 30); do
    if php -r "
        \$host = getenv('DB_HOST') ?: '127.0.0.1';
        \$port = getenv('DB_PORT') ?: '5432';
        \$db = getenv('DB_DATABASE') ?: 'gestdepense';
        \$user = getenv('DB_USERNAME') ?: 'gestdepense';
        \$pass = getenv('DB_PASSWORD') ?: 'secret';
        new PDO(\"pgsql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass);
    " 2>/dev/null; then
        echo "Database connected."
        break
    fi
    echo "Attempt $i/30 - waiting for database..."
    sleep 2
done

# Exécution des migrations
php artisan migrate --force

# Démarrage de Nginx en arrière-plan
nginx -g 'daemon off;' &
NGINX_PID=$!

# Démarrage de PHP-FPM au premier plan
php-fpm -F -R
