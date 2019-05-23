#!/bin/bash

# Tests are run directly in the container without mounted volumes
# expect if you run `bin/test.sh 1`
# A build is required after any modification.

set -ex

export APP_ENV=test

FILE=""
if [[ -z "$1" ]]; then
    FILE=" -f docker-compose.yml"
fi

docker-compose$FILE run --user app --rm upload_php /bin/sh -c "composer install --no-interaction && bin/console doctrine:schema:update -f && bin/phpunit"
docker-compose$FILE run --user app --rm auth_php /bin/sh -c "composer install --no-interaction && bin/console doctrine:schema:update -f && bin/phpunit"
