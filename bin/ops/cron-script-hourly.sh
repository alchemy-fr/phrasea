#!/bin/bash

. bin/vars.sh

. "bin/functions.sh"

load-env

echo $COMPOSE_PROFILES

if [[ ${COMPOSE_PROFILES} == *"databox"* ]]; then
  echo "Running databox hourly cron tasks..."
  docker compose run --rm databox-worker /srv/app/bin/cron-script-hourly.sh
fi
