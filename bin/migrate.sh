#!/bin/bash

set -e

. bin/functions.sh

load-env
check_default_secrets

set -ex

docker compose up -d

# Wait for services to be ready
docker compose run --rm dockerize
setup_elasticsearch_users
setup_minio_users

APPS="
expose-api-php
databox-api-php
uploader-api-php
"

for app in ${APPS}; do
    exec_container_as $app "bin/migrate.sh" app
done

run_container_as configurator "bin/migrate.sh" app
