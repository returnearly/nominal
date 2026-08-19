FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libzip-dev libicu-dev libsqlite3-dev iputils-ping \
    && docker-php-ext-install pcntl pdo_mysql pdo_sqlite zip intl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi || true

RUN chmod +x docker/entrypoint.sh

ENV CONTAINER_ROLE=app
EXPOSE 8000 8080

ENTRYPOINT ["docker/entrypoint.sh"]
