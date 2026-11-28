#!/bin/sh
set -eu

php artisan nominal:ensure-admin --no-interaction
exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8080 --admin-port=2019
