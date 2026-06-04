#!/bin/bash

. bin/functions.sh

load-env

set -ex

export XDEBUG_ENABLED=0
export VERIFY_SSL=false
export COMPOSE_PROFILES=db,redis,elasticsearch,report,minio

docker compose up -d
docker compose run --rm dockerize

SF_SERVICES="
databox-api-php
expose-api-php
uploader-api-php
"

for s in ${SF_SERVICES}; do
  APP_ENV=test docker compose run --rm -T ${s} su app -c "rm -rf bin/.phpunit && composer install --no-interaction && composer test"
done

. bin/vars.sh

excluded_dirs="api-test report-bundle report-sdk"

for lib in ${PHP_LIBS}; do
  dir=$(basename ${lib})
  if [[ ${excluded_dirs} =~ (^|[[:space:]])"$dir"($|[[:space:]]) ]] ; then
    echo "Skipping ${dir}"
    continue
  fi
  echo "Testing PHP lib: ${lib}"
  APP_ENV=test  docker compose run --rm -T databox-api-php su app -c "cd vendor/alchemy/${dir} && composer install --no-interaction && composer test"
done
