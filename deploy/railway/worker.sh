#!/bin/sh
set -eu

. /var/www/html/deploy/railway/wait-for-database.sh
wait_for_probes

region="${PROBE_REGION:-local}"
exec php artisan queue:work --queue="checks.${region},default" --sleep=1 --tries=1
