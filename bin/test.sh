#!/bin/bash

. bin/functions.sh

load-env

set -ex

export APP_ENV=test
export XDEBUG_ENABLED=0
export VERIFY_SSL=false
export COMPOSE_PROFILES=db,uploader,auth,report,databox,expose,notify

docker-compose up -d

docker-compose run --rm dockerize

SF_SERVICES="
databox-api-php
expose-api-php
auth-api-php
uploader-api-php
notify-api-php
"

for s in ${SF_SERVICES}; do
    docker-compose run -T --rm ${s} su app -c "composer install --ignore-platform-req=php --no-interaction && composer test"
done


LIBS="
admin-bundle
api-test
notify-bundle
oauth-server-bundle
remote-auth-bundle
report-bundle
report-sdk
"
for lib in ${LIBS}; do
    docker-compose run -T --rm auth-api-php su app -c "cd vendor/alchemy/${lib} && composer install --ignore-platform-req=php --no-interaction && composer test"
done

docker-compose run -T --rm databox-api-php su app -c "cd vendor/alchemy/workflow && composer install --ignore-platform-req=php --no-interaction && composer test"
