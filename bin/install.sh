#!/bin/bash

BASEDIR=$(dirname $0)

. "$BASEDIR/load.env.sh"

"$BASEDIR/update-config.sh"


APP_ENV=${APP_ENV:-"prod"}

CONF=""
if [ ${APP_ENV} == "prod" ]; then
    CONF="-f docker-compose.yml"
fi

set -ex

docker-compose ${CONF} up -d

# Wait for services to be ready
docker-compose ${CONF} run --rm dockerize

docker-compose ${CONF} exec -T rabbitmq /bin/sh -c \
    "rabbitmqctl add_vhost auth; rabbitmqctl add_vhost upload; rabbitmqctl set_permissions -p auth ${RABBITMQ_DEFAULT_USER} '.*' '.*' '.*'; rabbitmqctl set_permissions -p upload ${RABBITMQ_DEFAULT_USER} '.*' '.*' '.*'"
docker-compose ${CONF} exec -T upload_php /bin/sh -c \
    "bin/console rabbitmq:setup-fabric; chown -R app: /var/data/upload"
docker-compose ${CONF} exec -T auth_php /bin/sh -c \
    "bin/console rabbitmq:setup-fabric"
docker-compose ${CONF} exec -T upload_php /bin/sh -c \
    "bin/console doctrine:database:create --if-not-exists; bin/console doctrine:schema:update -f"
docker-compose ${CONF} exec -T auth_php /bin/sh -c \
    "bin/console doctrine:database:create --if-not-exists; bin/console doctrine:schema:update -f"
docker-compose ${CONF} exec -T auth_php /bin/sh -c \
    "bin/console app:create-client ${UPLOAD_ADMIN_CLIENT_ID} --random-id=${UPLOAD_ADMIN_CLIENT_RANDOM_ID} --secret=${UPLOAD_ADMIN_CLIENT_SECRET} --grant-type password;bin/console app:create-client ${CLIENT_ID} --random-id=${CLIENT_RANDOM_ID} --secret=${CLIENT_SECRET} --grant-type password --grant-type authorization_code; bin/console app:user:create --update-if-exist ${DEFAULT_USER_EMAIL} -p ${DEFAULT_USER_PASSWORD} --roles ROLE_SUPER_ADMIN"
