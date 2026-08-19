FROM serversideup/php:8.5-frankenphp

USER root
RUN install-php-extensions intl \
    && apt-get update \
    && apt-get install -y --no-install-recommends iputils-ping \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

USER www-data

COPY --chown=www-data:www-data . /var/www/html

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

ENV PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_OPCACHE_REVALIDATE_FREQ=0 \
    OCTANE_SERVER=frankenphp \
    HEALTHCHECK_PATH=/up

CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8080", "--admin-port=2019"]

HEALTHCHECK --interval=10s --timeout=3s --start-period=10s --retries=3 CMD ["healthcheck-octane"]

EXPOSE 8080 8443
