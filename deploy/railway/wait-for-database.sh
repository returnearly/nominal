#!/bin/sh

wait_for_database() {
    i=0
    until php artisan db:show --no-interaction >/dev/null 2>&1; do
        i=$((i + 1))
        if [ "$i" -gt 90 ]; then
            echo "Timed out waiting for the database." >&2
            exit 1
        fi
        sleep 2
    done
}

wait_for_probes() {
    i=0
    until php artisan db:table probes --json --no-interaction >/dev/null 2>&1; do
        i=$((i + 1))
        if [ "$i" -gt 90 ]; then
            echo "Timed out waiting for the probes table." >&2
            exit 1
        fi
        sleep 2
    done
}
