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

APPS="
auth-api-php
expose-api-php
notify-api-php
databox-api-php
uploader-api-php
"

for app in ${APPS}; do
    exec_container $app "bin/migrate.sh"
done
