#!/bin/bash

set -e

. bin/functions.sh
load-env

CLIENTS="databox expose uploader"

for c in ${CLIENTS}; do
  echo " Clearing Vite cache in $c"
  rm -rf ${c}/client/node_modules/.vite
  docker compose restart ${c}-client
done

echo " Clearing Vite cache in dashboard"
rm -rf dashboard/client/node_modules/.vite
docker compose restart dashboard
