#!/bin/bash

set -e

export APP_ENV=test

docker-compose -f docker-compose.yml run --rm upload_php composer install
docker-compose -f docker-compose.yml run --rm upload_php bin/phpunit
docker-compose -f docker-compose.yml run --rm auth_php composer install
docker-compose -f docker-compose.yml run --rm auth_php bin/phpunit
