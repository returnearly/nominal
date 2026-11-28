#!/bin/sh
set -eu

case "${NOMINAL_PROCESS:-web}" in
    web) exec healthcheck-octane ;;
    worker) exec healthcheck-queue ;;
    scheduler) exec healthcheck-schedule ;;
    reverb) exec healthcheck-reverb ;;
    *)
        echo "Unknown NOMINAL_PROCESS: ${NOMINAL_PROCESS}" >&2
        exit 1
        ;;
esac
