#!/bin/bash

set -ex

export APP_ENV=test

docker-compose -f docker-compose.yml run --user app --rm upload_php composer install
docker-compose -f docker-compose.yml run --user app --rm upload_php bin/phpunit
docker-compose -f docker-compose.yml run --user app --rm auth_php composer install
docker-compose -f docker-compose.yml run --user app --rm auth_php bin/phpunit
