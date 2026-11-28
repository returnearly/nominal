#!/bin/sh
set -eu

. /var/www/html/deploy/railway/wait-for-database.sh
wait_for_probes

exec php artisan schedule:work
