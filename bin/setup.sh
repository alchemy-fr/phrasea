#!/bin/bash

set -e

BASEDIR=$(dirname $0)
. "$BASEDIR/functions.sh"
cd "$BASEDIR/.."

load-env

"$BASEDIR/update-config.sh"

set -ex

docker-compose up -d

# Wait for services to be ready
docker-compose run --rm dockerize

# Setup Auth
## Create rabbitmq vhost
exec_container rabbitmq "rabbitmqctl add_vhost auth && rabbitmqctl set_permissions -p auth ${RABBITMQ_USER} '.*' '.*' '.*'"
## Setup container
exec_container_as auth-api-php "bin/setup.sh" app
## Create OAuth client for Admin
exec_container auth-api-php "bin/console alchemy:oauth:create-client ${AUTH_ADMIN_CLIENT_ID} \
    --random-id=${AUTH_ADMIN_CLIENT_RANDOM_ID} \
    --secret=${AUTH_ADMIN_CLIENT_SECRET} \
    --grant-type authorization_code \
    --redirect-uri ${AUTH_BASE_URL}"


# Setup Uploader
## Create rabbitmq vhost
exec_container rabbitmq "rabbitmqctl add_vhost upload && rabbitmqctl set_permissions -p upload ${RABBITMQ_USER} '.*' '.*' '.*'"
## Setup container
exec_container_as uploader-api-php "bin/setup.sh" app
## Create OAuth client
exec_container auth-api-php "bin/console alchemy:oauth:create-client ${UPLOADER_CLIENT_ID} \
    --random-id=${UPLOADER_CLIENT_RANDOM_ID} \
    --secret=${UPLOADER_CLIENT_SECRET} \
    --grant-type authorization_code \
    --redirect-uri ${UPLOADER_FRONT_BASE_URL}"
## Create OAuth client for Admin
exec_container auth-api-php "bin/console alchemy:oauth:create-client ${UPLOADER_ADMIN_CLIENT_ID} \
    --random-id=${UPLOADER_ADMIN_CLIENT_RANDOM_ID} \
    --secret=${UPLOADER_ADMIN_CLIENT_SECRET} \
    --grant-type authorization_code \
    --grant-type client_credentials \
    --scope user:list \
    --scope group:list \
    --redirect-uri ${UPLOADER_API_BASE_URL}"
## Create minio bucket
docker-compose ${CONF} run --rm -T --entrypoint "sh -c" minio-mc "\
  while ! nc -z minio 9000; do echo 'Wait minio to startup...' && sleep 0.1; done; \
  sleep 5 && \
  mc config host add minio http://minio:9000 \$MINIO_ACCESS_KEY \$MINIO_SECRET_KEY && \
  mc mb --ignore-existing minio/$UPLOADER_STORAGE_BUCKET_NAME \
"

# Setup Expose
## Create rabbitmq vhost
exec_container rabbitmq "rabbitmqctl add_vhost expose && rabbitmqctl set_permissions -p expose ${RABBITMQ_USER} '.*' '.*' '.*'"
## Setup container
exec_container_as expose-api-php "bin/setup.sh" app
## Create OAuth client
exec_container auth-api-php "bin/console alchemy:oauth:create-client ${EXPOSE_CLIENT_ID} \
    --random-id=${EXPOSE_CLIENT_RANDOM_ID} \
    --secret=${EXPOSE_CLIENT_SECRET} \
    --grant-type password"
## Create OAuth client for Admin
exec_container auth-api-php "bin/console alchemy:oauth:create-client ${EXPOSE_ADMIN_CLIENT_ID} \
    --random-id=${EXPOSE_ADMIN_CLIENT_RANDOM_ID} \
    --secret=${EXPOSE_ADMIN_CLIENT_SECRET} \
    --grant-type authorization_code \
    --grant-type client_credentials \
    --scope user:list \
    --scope group:list \
    --redirect-uri ${EXPOSE_API_BASE_URL}"
## Create minio bucket
docker-compose ${CONF} run --rm -T --entrypoint "sh -c" minio-mc "\
  i=0
  while ! nc -z minio 9000; do \
    echo 'Wait for minio to startup...'; \
    echo \$i; \
    if [ \$i -gt 180 ]; then \
      echo 'Timeout'; \
      exit 1; \
    fi; \
    i=\$((i+1)); \
    sleep 0.1; \
  done; \
  sleep 3 \
  && mc config host add minio http://minio:9000 \$MINIO_ACCESS_KEY \$MINIO_SECRET_KEY \
  && mc mb --ignore-existing minio/$EXPOSE_STORAGE_BUCKET_NAME \
"

# Setup Notify
## Create rabbitmq vhost
exec_container rabbitmq "rabbitmqctl add_vhost notify && rabbitmqctl set_permissions -p notify ${RABBITMQ_USER} '.*' '.*' '.*'"
## Setup container
exec_container_as notify-api-php "bin/setup.sh" app
## Create OAuth client for Notify Admin
exec_container auth-api-php "bin/console alchemy:oauth:create-client ${NOTIFY_ADMIN_CLIENT_ID} \
    --random-id=${NOTIFY_ADMIN_CLIENT_RANDOM_ID} \
    --secret=${NOTIFY_ADMIN_CLIENT_SECRET} \
    --grant-type authorization_code \
    --redirect-uri ${NOTIFY_BASE_URL}"

# Setup Databox
## Create rabbitmq vhost
exec_container rabbitmq "rabbitmqctl add_vhost databox && rabbitmqctl set_permissions -p databox ${RABBITMQ_USER} '.*' '.*' '.*'"
## Setup container
exec_container_as databox-api-php "bin/setup.sh" app
## Create OAuth client for Databox Admin
exec_container auth-api-php "bin/console alchemy:oauth:create-client ${DATABOX_ADMIN_CLIENT_ID} \
    --random-id=${DATABOX_ADMIN_CLIENT_RANDOM_ID} \
    --secret=${DATABOX_ADMIN_CLIENT_SECRET} \
    --grant-type authorization_code \
    --grant-type client_credentials \
    --scope user:list \
    --scope group:list \
    --redirect-uri ${DATABOX_API_BASE_URL}"
## Create OAuth client
exec_container auth-api-php "bin/console alchemy:oauth:create-client ${DATABOX_CLIENT_ID} \
    --random-id=${DATABOX_CLIENT_RANDOM_ID} \
    --secret=${DATABOX_CLIENT_SECRET} \
    --grant-type authorization_code \
    --redirect-uri ${DATABOX_CLIENT_BASE_URL}"

# Setup Report
## Create DB
create_db "${REPORT_DB_NAME}"


# Setup Weblate
## Create DB
create_db "${WEBLATE_POSTGRES_DB}"

# Create default admin user in Auth (must be execute after Notify & Auth setup)
exec_container auth-api-php "bin/console app:user:create \
    --update-if-exist ${DEFAULT_USER_EMAIL} \
    -p ${DEFAULT_USER_PASSWORD} \
    --roles ROLE_SUPER_ADMIN"

## Setup Zippy
exec_container rabbitmq "rabbitmqctl add_vhost zippy && rabbitmqctl set_permissions -p zippy ${RABBITMQ_USER} '.*' '.*' '.*'"
