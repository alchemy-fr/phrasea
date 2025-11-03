#!/bin/bash

. bin/vars.sh

. "bin/functions.sh"

load-env

echo $COMPOSE_PROFILES

if [[ ${COMPOSE_PROFILES} == *"zippy"* ]]; then
  echo "Running zippy cron tasks..."
  docker compose run --rm zippy-worker /srv/app/docker/cron/cron-script.sh
fi

if [[ ${COMPOSE_PROFILES} == *"databox"* ]]; then
  echo "Running databox cron tasks..."
  docker compose run --rm databox-worker /srv/app/bin/cron-script.sh
fi
