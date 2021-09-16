#!/bin/bash

BASEDIR=$(dirname $0)
. "$BASEDIR/functions.sh"

load-env

helm install \
    --dry-run \
    --generate-name \
    ./infra/helm/ps \
    -f ./infra/helm/sample.yaml

set -ex

export APP_ENV=test
export XDEBUG_ENABLED=0

SF_SERVICES="
expose-api-php
databox-api-php
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
