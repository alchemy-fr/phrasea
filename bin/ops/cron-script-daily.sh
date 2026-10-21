#!/bin/bash

. bin/vars.sh

. "bin/functions.sh"

load-env

echo $COMPOSE_PROFILES

if [[ ${COMPOSE_PROFILES} == *"zippy"* ]]; then
  echo "Running zippy daily cron tasks..."
  docker compose run --rm zippy-worker /srv/app/docker/cron/cron-script.sh
fi
