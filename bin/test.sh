#!/bin/bash

. bin/functions.sh

load-env

set -ex

export APP_ENV=test
export XDEBUG_ENABLED=0
export VERIFY_SSL=false
export COMPOSE_PROFILES=db,uploader,auth,report,databox,expose,notify

docker compose up -d

docker compose run --rm dockerize

SF_SERVICES="
databox-api-php
expose-api-php
auth-api-php
uploader-api-php
notify-api-php
"

for s in ${SF_SERVICES}; do
    docker compose run -T --rm ${s} su app -c "composer install --no-interaction && composer test"
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
    docker compose run -T --rm auth-api-php su app -c "cd vendor/alchemy/${lib} && composer install --no-interaction && composer test"
done

# TODO remove debug below:
docker-compose exec --rm databox-api-php pwd
docker-compose run -T --rm databox-api-php ls -la vendor/alchemy || echo bad
docker compose exec --rm databox-api-php pwd
docker compose run -T --rm databox-api-php pwd
docker compose run -T --rm databox-api-php ls -la vendor/alchemy
# TODO end of debug

docker compose run -T --rm databox-api-php su app -c "cd vendor/alchemy/workflow && composer install --no-interaction && composer test"
