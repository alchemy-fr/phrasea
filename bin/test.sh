#!/bin/bash

# Tests are run directly in the container without mounted volumes
# except if you run `bin/test.sh 1`
# A build is required after any modification.

set -ex

export APP_ENV=test

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
    docker-compose$FILE run -T --user app --rm ${s} /bin/sh -c "composer install --no-interaction && bin/console doctrine:schema:update -f && bin/phpunit"
done

# TODO make this work in CircleCI (which has no mounted volumes)
#REACT_SERVICES="
#expose-front-dev
#"
#for s in ${REACT_SERVICES}; do
#    # No use of $FILE because we need to load _dev containers (defined in docker-compose.override.yml)
#    docker-compose run -T --rm ${s} /bin/sh -c "CI=1 yarn test"
#done
