#!/bin/bash

set -e

. bin/functions.sh

load-env

set -ex

docker compose up -d

# Wait for services to be ready
docker compose run --rm dockerize

APPS="
expose-api-php
databox-api-php
uploader-api-php
"

for app in ${APPS}; do
    exec_container $app "bin/migrate.sh"
done

docker compose run --rm configurator bin/migrate.sh
