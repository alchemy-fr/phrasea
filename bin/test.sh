#!/bin/bash

# Tests are run directly in the container without mounted volumes.
# A build is required after any modification.

set -ex

export APP_ENV=test

docker-compose run --user app --rm upload_php /bin/sh -c "composer install --no-interaction && bin/phpunit"
docker-compose run --user app --rm auth_php /bin/sh -c "composer install --no-interaction && bin/console doctrine:schema:update -f && bin/phpunit"
