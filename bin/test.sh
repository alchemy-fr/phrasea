#!/bin/bash

set -e

docker-compose -f docker-compose.yml run --rm upload_php bin/phpunit
docker-compose -f docker-compose.yml run --rm auth_php bin/phpunit
