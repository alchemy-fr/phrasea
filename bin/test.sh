#!/bin/bash

. bin/functions.sh

load-env

set -ex

export APP_ENV=test
export XDEBUG_ENABLED=0
export VERIFY_SSL=false
export COMPOSE_PROFILES=db,uploader,report,databox,expose

docker compose up -d

docker compose run --rm dockerize

SF_SERVICES="
databox-api-php
expose-api-php
uploader-api-php
"

for s in ${SF_SERVICES}; do
  docker compose run -T --rm ${s} su app -c "composer install --no-interaction && composer test"
done

. bin/vars.sh

export APP_ENV=test
for lib in ${PHP_LIBS}; do
  dir=$(basename ${lib})
  docker compose run -T --rm databox-api-php su app -c "cd vendor/alchemy/${dir} && composer install --no-interaction && composer test"
done
