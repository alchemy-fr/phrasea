#!/bin/bash

set -e

. bin/functions.sh

load-env

bin/create-config.sh

set -ex

docker compose up -d

# Wait for services to be ready
docker compose run --rm dockerize

# Setup Report
## Create DB
create_db "${REPORT_DB_NAME}"
create_db "${KEYCLOAK_DB_NAME}"
create_db "${KEYCLOAK2_DB_NAME}"

## Create minio bucket
COMPOSE_PROFILES="${COMPOSE_PROFILES},setup" docker compose run --rm -T --entrypoint "sh -c" minio-mc "\
  while ! nc -z minio 9000; do echo 'Wait minio to startup...' && sleep 0.1; done; \
  sleep 5 && \
  mc config host add minio http://minio:9000 \$MINIO_ACCESS_KEY \$MINIO_SECRET_KEY && \
  mc mb --ignore-existing minio/$CONFIGURATOR_S3_BUCKET_NAME \
"

# Setup Uploader
## Create rabbitmq vhost
exec_container rabbitmq "rabbitmqctl add_vhost ${UPLOADER_RABBITMQ_VHOST} && rabbitmqctl set_permissions -p ${UPLOADER_RABBITMQ_VHOST} ${RABBITMQ_USER} '.*' '.*' '.*'"
## Setup container
exec_container_as uploader-api-php "bin/setup.sh" app
## Create minio bucket
COMPOSE_PROFILES="${COMPOSE_PROFILES},setup" docker compose run --rm -T --entrypoint "sh -c" minio-mc "\
  while ! nc -z minio 9000; do echo 'Wait minio to startup...' && sleep 0.1; done; \
  sleep 5 && \
  mc config host add minio http://minio:9000 \$MINIO_ACCESS_KEY \$MINIO_SECRET_KEY && \
  mc mb --ignore-existing minio/$UPLOADER_S3_BUCKET_NAME \
"

# Setup Expose
## Create rabbitmq vhost
exec_container rabbitmq "rabbitmqctl add_vhost ${EXPOSE_RABBITMQ_VHOST} && rabbitmqctl set_permissions -p ${EXPOSE_RABBITMQ_VHOST} ${RABBITMQ_USER} '.*' '.*' '.*'"
## Setup container
exec_container_as expose-api-php "bin/setup.sh" app
## Create minio bucket
COMPOSE_PROFILES="${COMPOSE_PROFILES},setup" docker compose run --rm -T --entrypoint "sh -c" minio-mc "\
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
  && mc mb --ignore-existing minio/$EXPOSE_S3_BUCKET_NAME \
"

# Setup Databox
## Create rabbitmq vhost
exec_container rabbitmq "rabbitmqctl add_vhost ${DATABOX_RABBITMQ_VHOST} && rabbitmqctl set_permissions -p ${DATABOX_RABBITMQ_VHOST} ${RABBITMQ_USER} '.*' '.*' '.*'"
## Setup container
exec_container_as databox-api-php "bin/setup.sh" app
## Create minio bucket
COMPOSE_PROFILES="${COMPOSE_PROFILES},setup" docker compose run --rm -T --entrypoint "sh -c" minio-mc "\
  while ! nc -z minio 9000; do echo 'Wait minio to startup...' && sleep 0.1; done; \
  sleep 5 && \
  mc config host add minio http://minio:9000 \$MINIO_ACCESS_KEY \$MINIO_SECRET_KEY && \
  mc mb --ignore-existing minio/$DATABOX_S3_BUCKET_NAME \
"
## Create Uploader target for client upload
exec_container uploader-api-php "bin/console app:create-target ${DATABOX_UPLOADER_TARGET_SLUG} 'Databox Uploader' http://databox-api/incoming-uploads"

## Setup indexer
## Create Databox OAuth client for indexer
COMPOSE_PROFILES="${COMPOSE_PROFILES},setup" docker compose run --rm -T --entrypoint "sh -c" minio-mc "\
  while ! nc -z minio 9000; do echo 'Wait minio to startup...' && sleep 0.1; done; \
  sleep 5 && \
  mc config host add minio http://minio:9000 \$MINIO_ACCESS_KEY \$MINIO_SECRET_KEY && \
  mc mb --ignore-existing minio/${INDEXER_BUCKET_NAME} \
"
exec_container rabbitmq "rabbitmqctl add_vhost s3events && rabbitmqctl set_permissions -p s3events ${RABBITMQ_USER} '.*' '.*' '.*'"
exec_container rabbitmq "\
  rabbitmqadmin declare exchange --vhost=s3events name=s3events type=direct durable='true' -u ${RABBITMQ_USER} -p ${RABBITMQ_PASSWORD} \
  && rabbitmqadmin declare queue --vhost=s3events name=s3events auto_delete=false durable='true' -u ${RABBITMQ_USER} -p ${RABBITMQ_PASSWORD} \
  && rabbitmqadmin declare binding --vhost=s3events source=s3events destination=s3events routing_key='' -u ${RABBITMQ_USER} -p ${RABBITMQ_PASSWORD}"
COMPOSE_PROFILES="${COMPOSE_PROFILES},setup" docker compose run --rm -T --entrypoint "sh -c" minio-mc "\
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

docker compose restart keycloak
docker compose run --rm dockerize -wait http://keycloak:9000/health/ready -timeout 200s

docker compose run --rm configurator bin/setup.sh

PRESETS=""
for p in $@; do
  PRESETS="${PRESETS} --preset $p"
done
docker compose run --rm configurator bin/console configure -vvv$PRESETS

echo "Done."
