# syntax=docker/dockerfile:1

FROM --platform=$BUILDPLATFORM node:26-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci

COPY vite.config.js ./
COPY resources ./resources

RUN mkdir -p public && npm run build

FROM serversideup/php:8.5-frankenphp AS php-base

USER root
RUN install-php-extensions intl \
    && apt-get update \
    && apt-get install -y --no-install-recommends iputils-ping \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

USER www-data
WORKDIR /var/www/html

ENV PHP_OPCACHE_ENABLE=1 \
    OCTANE_SERVER=frankenphp \
    HEALTHCHECK_PATH=/up \
    COMPOSER_IPRESOLVE=4 \
    COMPOSER_MAX_PARALLEL_HTTP=6

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8080", "--admin-port=2019"]
HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 CMD ["healthcheck-octane"]
EXPOSE 8080 8443

FROM php-base AS app

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN --mount=type=secret,id=github_token,required=false,mode=0444 \
    if [ -f /run/secrets/github_token ]; then \
      export COMPOSER_AUTH="{\"github-oauth\":{\"github.com\":\"$(cat /run/secrets/github_token)\"}}"; \
    fi \
    && composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader \
    || composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader \
    || composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY --chown=www-data:www-data . /var/www/html

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p bootstrap/cache \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs

FROM app AS development

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=1 \
    PHP_OPCACHE_REVALIDATE_FREQ=0

FROM app AS production

COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_OPCACHE_REVALIDATE_FREQ=0
