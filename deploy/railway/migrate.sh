#!/bin/sh
set -eu

. /var/www/html/deploy/railway/wait-for-database.sh
wait_for_database

php artisan migrate --force --no-interaction
php artisan db:seed --class='Database\Seeders\DefaultProbeSeeder' --force --no-interaction
