#!/bin/bash

# Tests are run directly in the container without mounted volumes
# except if you run `bin/test.sh 1`
# A build is required after any modification.

set -ex

export APP_ENV=test
export XDEBUG_ENABLED=0

FILE=""
if [[ -z "$1" ]]; then
    FILE=" -f docker-compose.yml"
fi


SF_SERVICES="
uploader-api-php
auth-api-php
expose-api-php
notify-api-php
"

for s in ${SF_SERVICES}; do
    docker-compose$FILE run -T --user app --rm ${s} /bin/sh -c "composer install --no-interaction && composer test"
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
    docker-compose$FILE run -T --user app --rm auth-api-php /bin/sh -c "cd vendor/alchemy/${lib} && composer install --no-interaction && composer test"
done

# TODO make this work in CircleCI (which has no mounted volumes)
#REACT_SERVICES="
#expose-front-dev
#"
#for s in ${REACT_SERVICES}; do
#    # No use of $FILE because we need to load _dev containers (defined in docker-compose.override.yml)
#    docker-compose run -T --rm ${s} /bin/sh -c "CI=1 yarn test"
#done
