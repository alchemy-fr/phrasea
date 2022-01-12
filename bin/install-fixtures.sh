#!/bin/bash

set -e

. bin/functions.sh

load-env

set -ex

export COMPOSE_PROFILES=setup,db,databox,auth

docker-compose up -d

# Wait for services to be ready
docker-compose run --rm dockerize

exec_container_as auth-api-php "bin/console hautelook:fixtures:load --no-interaction" app
exec_container_as databox-api-php "bin/console hautelook:fixtures:load --no-interaction" app

bin/setup.sh
