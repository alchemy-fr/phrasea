#!/bin/bash

BASEDIR=$(dirname $0)

. "$BASEDIR/load.env.sh"

set -ex

export APP_ENV=prod
CONF="-f docker-compose.yml"

docker-compose ${CONF} up -d

sleep 10

docker-compose ${CONF} exec rabbitmq /bin/sh -c \
    "rabbitmqctl add_vhost auth; rabbitmqctl add_vhost upload; rabbitmqctl set_permissions -p auth ${RABBITMQ_DEFAULT_USER} '.*' '.*' '.*'; rabbitmqctl set_permissions -p upload ${RABBITMQ_DEFAULT_USER} '.*' '.*' '.*'"
docker-compose ${CONF} exec upload_php /bin/sh -c \
    "bin/console rabbitmq:setup-fabric"
docker-compose ${CONF} exec auth_php /bin/sh -c \
    "bin/console rabbitmq:setup-fabric"
docker-compose ${CONF} exec upload_php /bin/sh -c \
    "bin/console doctrine:database:create --if-not-exists; bin/console doctrine:schema:update -f"
docker-compose ${CONF} exec auth_php /bin/sh -c \
    "bin/console doctrine:database:create --if-not-exists; bin/console doctrine:schema:update -f"
docker-compose ${CONF} exec auth_php /bin/sh -c \
    "bin/console app:create-client ${CLIENT_ID} --random-id=${CLIENT_RANDOM_ID} --secret=${CLIENT_SECRET} --grant-type password; bin/console app:create-user --update-if-exist ${DEFAULT_USER_EMAIL} -p ${DEFAULT_USER_PASSWORD}"
