#!/bin/bash

BASEDIR=$(dirname $0)
. "$BASEDIR/functions.sh"
cd "$BASEDIR/.."

# Load vars from env.local
load-env-local

# export variables from env files
export_from_env_file APP_ENV
export_from_env_file WEBLATE_POSTGRES_DB
export_from_env_file REPORT_DB_NAME

APP_ENV=${APP_ENV:-"prod"}

"$BASEDIR/update-config.sh"

set -ex

d-c up -d

# Wait for services to be ready
d-c run --rm dockerize

exec_container rabbitmq         "/setup.sh"
exec_container uploader-api-php "bin/rabbitmq-setup-fabric.sh"
exec_container expose-api-php   "bin/doctrine-database-create.sh"
exec_container notify-api-php   "bin/doctrine-database-create.sh"
exec_container auth-api-php     "bin/install.sh"

# Create expose bucket
d-c run --rm -T --entrypoint "sh -c" minio-mc "/setup.sh"

# Weblate
exec_container db "/create-database.sh ${WEBLATE_POSTGRES_DB}"

# Report
exec_container db "/create-database.sh ${REPORT_DB_NAME}"
exec_container db "/create-database.sh ${REPORT_DB_NAME}" < "$BASEDIR/../report/structure.sql"
