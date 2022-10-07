#!/bin/bash

set -e

. bin/functions.sh

load-env

bin/update-config.sh

set -ex

export COMPOSE_PROFILES=setup,db,uploader,auth,databox,expose,notify,dashboard,tools
export AUTH_API_BASE_URL=https://api-auth.${PHRASEA_DOMAIN}
export UPLOADER_CLIENT_BASE_URL=https://uploader.${PHRASEA_DOMAIN}
export DATABOX_CLIENT_BASE_URL=https://databox.${PHRASEA_DOMAIN}
export UPLOADER_API_BASE_URL=https://api-uploader.${PHRASEA_DOMAIN}
export DATABOX_API_BASE_URL=https://api-databox.${PHRASEA_DOMAIN}
export NOTIFY_API_BASE_URL=https://api-notify.${PHRASEA_DOMAIN}
export EXPOSE_API_BASE_URL=https://api-expose.${PHRASEA_DOMAIN}

docker-compose up -d

# Wait for services to be ready
docker-compose run --rm dockerize

# Setup Auth
## Create rabbitmq vhost
exec_container rabbitmq "rabbitmqctl add_vhost ${AUTH_RABBITMQ_VHOST} && rabbitmqctl set_permissions -p ${AUTH_RABBITMQ_VHOST} ${RABBITMQ_USER} '.*' '.*' '.*'"
## Setup container
exec_container_as auth-api-php "bin/setup.sh" app
## Create OAuth client for Admin
exec_container auth-api-php "bin/console alchemy:oauth:create-client ${AUTH_ADMIN_CLIENT_ID} \
    --random-id=${AUTH_ADMIN_CLIENT_RANDOM_ID} \
    --secret=${AUTH_ADMIN_CLIENT_SECRET} \
    --grant-type authorization_code \
    --redirect-uri ${AUTH_API_BASE_URL}"


# Setup Uploader
## Create rabbitmq vhost
exec_container rabbitmq "rabbitmqctl add_vhost ${UPLOADER_RABBITMQ_VHOST} && rabbitmqctl set_permissions -p ${UPLOADER_RABBITMQ_VHOST} ${RABBITMQ_USER} '.*' '.*' '.*'"
## Setup container
exec_container_as uploader-api-php "bin/setup.sh" app
## Create OAuth client
exec_container auth-api-php "bin/console alchemy:oauth:create-client ${UPLOADER_CLIENT_ID} \
    --random-id=${UPLOADER_CLIENT_RANDOM_ID} \
    --secret=${UPLOADER_CLIENT_SECRET} \
    --grant-type authorization_code \
    --redirect-uri ${UPLOADER_CLIENT_BASE_URL}"
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
docker-compose run --rm -T --entrypoint "sh -c" minio-mc "\
  while ! nc -z minio 9000; do echo 'Wait minio to startup...' && sleep 0.1; done; \
  sleep 5 && \
  mc config host add minio http://minio:9000 \$MINIO_ACCESS_KEY \$MINIO_SECRET_KEY && \
  mc mb --ignore-existing minio/$UPLOADER_STORAGE_BUCKET_NAME \
"

# Setup Expose
## Create rabbitmq vhost
exec_container rabbitmq "rabbitmqctl add_vhost ${EXPOSE_RABBITMQ_VHOST} && rabbitmqctl set_permissions -p ${EXPOSE_RABBITMQ_VHOST} ${RABBITMQ_USER} '.*' '.*' '.*'"
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
docker-compose run --rm -T --entrypoint "sh -c" minio-mc "\
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
exec_container rabbitmq "rabbitmqctl add_vhost ${NOTIFY_RABBITMQ_VHOST} && rabbitmqctl set_permissions -p ${NOTIFY_RABBITMQ_VHOST} ${RABBITMQ_USER} '.*' '.*' '.*'"
## Setup container
exec_container_as notify-api-php "bin/setup.sh" app
## Create OAuth client for Notify Admin
exec_container auth-api-php "bin/console alchemy:oauth:create-client ${NOTIFY_ADMIN_CLIENT_ID} \
    --random-id=${NOTIFY_ADMIN_CLIENT_RANDOM_ID} \
    --secret=${NOTIFY_ADMIN_CLIENT_SECRET} \
    --grant-type authorization_code \
    --redirect-uri ${NOTIFY_API_BASE_URL}"

# Setup Databox
## Create rabbitmq vhost
exec_container rabbitmq "rabbitmqctl add_vhost ${DATABOX_RABBITMQ_VHOST} && rabbitmqctl set_permissions -p ${DATABOX_RABBITMQ_VHOST} ${RABBITMQ_USER} '.*' '.*' '.*'"
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
## Create minio bucket
docker-compose run --rm -T --entrypoint "sh -c" minio-mc "\
  while ! nc -z minio 9000; do echo 'Wait minio to startup...' && sleep 0.1; done; \
  sleep 5 && \
  mc config host add minio http://minio:9000 \$MINIO_ACCESS_KEY \$MINIO_SECRET_KEY && \
  mc mb --ignore-existing minio/\$DATABOX_STORAGE_BUCKET_NAME \
"
## Create Uploader target for client upload
exec_container uploader-api-php "bin/console app:create-target ${DATABOX_UPLOADER_TARGET_SLUG} 'Databox Uploader' http://databox-api/incoming-uploads"

# Setup Report
## Create DB
create_db "${REPORT_DB_NAME}"


# Create default admin user in Auth (must be execute after Notify & Auth setup)
exec_container auth-api-php "bin/console app:user:create \
    --update-if-exist ${DEFAULT_USER_EMAIL} \
    -p ${DEFAULT_USER_PASSWORD} \
    --roles ROLE_SUPER_ADMIN"

## Setup indexer
## Create Databox OAuth client for indexer
docker-compose run --rm -T --entrypoint "sh -c" minio-mc "\
  while ! nc -z minio 9000; do echo 'Wait minio to startup...' && sleep 0.1; done; \
  sleep 5 && \
  mc config host add minio http://minio:9000 \$MINIO_ACCESS_KEY \$MINIO_SECRET_KEY && \
  mc mb --ignore-existing minio/${INDEXER_BUCKET_NAME} \
"
exec_container databox-api-php "bin/console alchemy:oauth:create-client ${INDEXER_DATABOX_CLIENT_ID} \
    --random-id=${INDEXER_DATABOX_CLIENT_RANDOM_ID} \
    --secret=${INDEXER_DATABOX_CLIENT_SECRET} \
    --grant-type authorization_code \
    --grant-type client_credentials \
    --scope chuck-norris"
exec_container rabbitmq "rabbitmqctl add_vhost s3events && rabbitmqctl set_permissions -p s3events ${RABBITMQ_USER} '.*' '.*' '.*'"
exec_container rabbitmq "\
  rabbitmqadmin declare exchange --vhost=s3events name=s3events type=direct durable='true' -u ${RABBITMQ_USER} -p ${RABBITMQ_PASSWORD} \
  && rabbitmqadmin declare queue --vhost=s3events name=s3events auto_delete=false durable='true' -u ${RABBITMQ_USER} -p ${RABBITMQ_PASSWORD} \
  && rabbitmqadmin declare binding --vhost=s3events source=s3events destination=s3events routing_key='' -u ${RABBITMQ_USER} -p ${RABBITMQ_PASSWORD}"
docker-compose run --rm -T --entrypoint "sh -c" minio-mc "\
  set -x; \
  while ! nc -z minio 9000; do echo 'Wait minio to startup...' && sleep 0.1; done; \
    mc config host add minio http://minio:9000 \$MINIO_ACCESS_KEY \$MINIO_SECRET_KEY \
    && mc admin config set minio notify_amqp:primary \
      url="amqp://${RABBITMQ_USER}:${RABBITMQ_PASSWORD}@rabbitmq:5672/s3events" \
      exchange="s3events" \
      exchange_type="direct" \
      durable="on" \
      delivery_mode=2 \
    && mc admin service restart minio/ \
    && (mc event add minio/${INDEXER_BUCKET_NAME} arn:minio:sqs::primary:amqp || echo ok)
"

echo "Done."
