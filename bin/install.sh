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

#docker-compose ${CONF} up -d
#
## Wait for services to be ready
#docker-compose ${CONF} run --rm dockerize

function exec_container() {
    docker-compose ${CONF} exec -T "$1" sh -c "$2"
}

exec_container rabbitmq "\
    rabbitmqctl add_vhost auth \
    && rabbitmqctl add_vhost upload \
    && rabbitmqctl set_permissions -p auth ${RABBITMQ_DEFAULT_USER} '.*' '.*' '.*' \
    && rabbitmqctl set_permissions -p upload ${RABBITMQ_DEFAULT_USER} '.*' '.*' '.*' \
"

exec_container uploader_api_php "\
    bin/console rabbitmq:setup-fabric \
    && chown -R app: /var/data/upload \
    && bin/console doctrine:database:create --if-not-exists \
    && bin/console doctrine:schema:update -f \
"

exec_container expose_api_php "\
    bin/console doctrine:database:create --if-not-exists \
    && bin/console doctrine:schema:update -f \
"

exec_container auth_api_php "\
    bin/console rabbitmq:setup-fabric \
    && bin/console doctrine:database:create --if-not-exists \
    && bin/console doctrine:schema:update -f \
    && bin/console app:create-client ${UPLOAD_ADMIN_CLIENT_ID} --random-id=${UPLOAD_ADMIN_CLIENT_RANDOM_ID} --secret=${UPLOAD_ADMIN_CLIENT_SECRET} --grant-type password \
    && bin/console app:create-client ${CLIENT_ID} --random-id=${CLIENT_RANDOM_ID} --secret=${CLIENT_SECRET} --grant-type password --grant-type authorization_code \
    && bin/console app:user:create --update-if-exist ${DEFAULT_USER_EMAIL} -p ${DEFAULT_USER_PASSWORD} --roles ROLE_SUPER_ADMIN \
"

# Create expose bucket
docker-compose ${CONF} run --rm -T --entrypoint "sh -c" minio_mc "\
  while ! nc -z minio 9000; do echo 'Wait minio to startup...' && sleep 0.1; done; \
  sleep 5 && \
  mc config host add minio http://minio:9000 \$MINIO_ACCESS_KEY \$MINIO_SECRET_KEY && \
  mc mb --ignore-existing minio/$EXPOSE_STORAGE_BUCKET_NAME && \
  mc policy set download minio/$EXPOSE_STORAGE_BUCKET_NAME \
"
