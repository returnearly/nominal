#!/usr/bin/env sh
set -eu

role="${CONTAINER_ROLE:-app}"
region="${PROBE_REGION:-local}"

if [ ! -f /app/vendor/autoload.php ]; then
    composer install --no-interaction
fi

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is required" >&2
    exit 1
fi

php artisan config:cache || true

case "$role" in
    app)
        php artisan migrate --force
        exec php artisan serve --host=0.0.0.0 --port=8000
        ;;
    worker)
        exec php artisan queue:work --queue="checks.${region},default" --sleep=1 --tries=1
        ;;
    scheduler)
        exec php artisan schedule:work
        ;;
    reverb)
        exec php artisan reverb:start --host=0.0.0.0 --port=8080
        ;;
    *)
        echo "Unknown CONTAINER_ROLE: ${role}" >&2
        exit 1
        ;;
esac
