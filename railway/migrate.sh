#!/bin/sh
# Apply schema, then create the default probe and first admin user.
# Retries while Postgres is still starting. Exits 0 so Railway keeps the deployment successful.
set -eu

attempts=0

until php artisan migrate --force; do
    attempts=$((attempts + 1))

    if [ "$attempts" -ge 30 ]; then
        echo "nominal: migrations did not finish after ${attempts} attempts" >&2
        exit 1
    fi

    echo "nominal: database is not ready (${attempts}/30), retrying" >&2
    sleep 5
done

php artisan nominal:provision
