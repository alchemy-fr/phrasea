#!/bin/bash

. bin/functions.sh

load-env

set -ex

export COMPOSE_PROJECT_NAME=test
export APP_ENV=test
export XDEBUG_ENABLED=0
export VERIFY_SSL=false
export COMPOSE_PROFILES=db,uploader,auth,report,databox,expose,notify

# Prepare network
PHRASEA_DOMAIN=phrasea.local bin/append-etc-hosts.sh
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
    docker-compose run -T --rm ${s} su app -c "composer install --no-interaction && composer test"
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
    docker-compose run -T --rm auth-api-php su app -c "cd vendor/alchemy/${lib} && composer install --no-interaction && composer test"
done

# TODO make this work in CircleCI (which has no mounted volumes)
#REACT_SERVICES="
#expose-client-dev
#"
#for s in ${REACT_SERVICES}; do
#    # No use of $FILE because we need to load _dev containers (defined in docker-compose.override.yml)
#    docker-compose run -T --rm ${s} /bin/sh -c "CI=1 yarn test"
#done
