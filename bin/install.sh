#!/bin/bash

BASEDIR=$(dirname $0)

. "$BASEDIR/load.env.sh"

export APP_ENV=prod

set -ex

docker-compose -f docker-compose.yml up -d \
    && sleep 10 \
    && docker-compose -f docker-compose.yml run --rm upload_php /bin/sh -c \
        "bin/console rabbitmq:setup-fabric" \
    && docker-compose -f docker-compose.yml run --rm upload_php /bin/sh -c \
        "bin/console doctrine:database:create --if-not-exists; bin/console doctrine:schema:update -f" \
    && docker-compose -f docker-compose.yml run --rm auth_php /bin/sh -c \
        "bin/console doctrine:database:create --if-not-exists; bin/console doctrine:schema:update -f" \
    && docker-compose -f docker-compose.yml run --rm auth_php /bin/sh -c \
        "bin/console app:create-client ${CLIENT_ID} --random-id=${CLIENT_RANDOM_ID} --secret=${CLIENT_SECRET} --grant-type password; bin/console app:create-user --update-if-exist ${DEFAULT_USER_EMAIL} -p ${DEFAULT_USER_PASSWORD}"
