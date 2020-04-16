#!/bin/bash

BASEDIR=$(dirname $0)
. "$BASEDIR/functions.sh"
cd "$BASEDIR/.."

load-env

"$BASEDIR/update-config.sh"

set -e

d-c up -d

# Wait for services to be ready
d-c run --rm dockerize

exec_container rabbitmq         "/setup.sh"
exec_container uploader-api-php "bin/setup.sh"
exec_container expose-api-php   "bin/setup.sh"
exec_container notify-api-php   "bin/setup.sh"
exec_container auth-api-php     "bin/setup.sh"

# Create OAuth client for Auth Admin
exec_container auth-api-php     "bin/console app:create-client ${AUTH_ADMIN_CLIENT_ID} \
    --random-id=${AUTH_ADMIN_CLIENT_RANDOM_ID} \
    --secret=${AUTH_ADMIN_CLIENT_SECRET} \
    --grant-type password \
    --grant-type authorization_code \
    --redirect-uri ${AUTH_BASE_URL}"

# Create OAuth client for Uploader
exec_container auth-api-php    "bin/console app:create-client ${UPLOADER_CLIENT_ID} \
    --random-id=${UPLOADER_CLIENT_RANDOM_ID} \
    --secret=${UPLOADER_CLIENT_SECRET} \
    --grant-type password \
    --grant-type authorization_code"
# Create OAuth client for Uploader Admin
exec_container auth-api-php     "bin/console app:create-client ${UPLOADER_ADMIN_CLIENT_ID} \
    --random-id=${UPLOADER_ADMIN_CLIENT_RANDOM_ID} \
    --secret=${UPLOADER_ADMIN_CLIENT_SECRET} \
    --grant-type password \
    --grant-type authorization_code \
    --redirect-uri ${UPLOADER_BASE_URL}"

# Create OAuth client for Expose
exec_container auth-api-php    "bin/console app:create-client ${EXPOSE_CLIENT_ID} \
    --random-id=${EXPOSE_CLIENT_RANDOM_ID} \
    --secret=${EXPOSE_CLIENT_SECRET} \
    --grant-type client_credentials \
    --grant-type authorization_code"
# Create OAuth client for Expose Admin
exec_container auth-api-php     "bin/console app:create-client ${EXPOSE_ADMIN_CLIENT_ID} \
    --random-id=${EXPOSE_ADMIN_CLIENT_RANDOM_ID} \
    --secret=${EXPOSE_ADMIN_CLIENT_SECRET} \
    --grant-type password \
    --grant-type authorization_code \
    --redirect-uri ${EXPOSE_BASE_URL}"
# Create OAuth client for Notify Admin
exec_container auth-api-php     "bin/console app:create-client ${NOTIFY_ADMIN_CLIENT_ID} \
    --random-id=${NOTIFY_ADMIN_CLIENT_RANDOM_ID} \
    --secret=${NOTIFY_ADMIN_CLIENT_SECRET} \
    --grant-type password \
    --grant-type authorization_code \
    --redirect-uri ${NOTIFY_BASE_URL}"

# Create default admin user
exec_container auth-api-php    "bin/console app:user:create \
    --update-if-exist ${DEFAULT_USER_EMAIL} \
    -p ${DEFAULT_USER_PASSWORD} \
    --roles ROLE_SUPER_ADMIN
"

# Create expose bucket
d-c run --rm -T --entrypoint "sh -c" minio-mc "/setup.sh"

# Weblate
exec_container db "/create-database.sh ${WEBLATE_POSTGRES_DB}"

# Report
exec_container db "/create-database.sh ${REPORT_DB_NAME}"
exec_container db "/create-database.sh ${REPORT_DB_NAME}" < "$BASEDIR/../report/structure.sql"
