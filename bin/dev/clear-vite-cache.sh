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

for a in "${js[@]}"; do
  docker compose run --rm ${a}-client rm -rf node_modules/.vite
  docker compose restart ${a}-client
done
