FROM php:8.4-fpm-alpine

# System dependencies
RUN apk add --no-cache nginx libzip-dev postgresql-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql mbstring zip opcache

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Application
COPY . .

# Create .env from example (required for key:generate)
RUN cp .env.example .env

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Permissions
RUN chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Nginx configuration
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# PHP-FPM config: listen on TCP 127.0.0.1:9000
RUN sed -i 's|listen = 9000|listen = 127.0.0.1:9000|' /usr/local/etc/php-fpm.d/zz-docker.conf

EXPOSE 8080

COPY start.sh /start.sh
RUN chmod +x /start.sh

CMD ["sh", "/start.sh"]
