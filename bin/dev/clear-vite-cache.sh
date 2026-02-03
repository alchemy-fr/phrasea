#!/bin/bash


. bin/functions.sh
load-env

set -e

js=(
  "dashboard"
  "databox"
  "expose"
  "uploader"
)

for c in "${js[@]}"; do
  echo " Clearing Vite cache in $c"
  docker compose run --rm ${c}-client rm -rf node_modules/.vite
  docker compose kill ${c}-client
  docker compose start ${c}-client
done
