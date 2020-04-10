#!/bin/sh

set -ex

BASEDIR=$(dirname $0)

$BASEDIR/console rabbitmq:setup-fabric
$BASEDIR/console doctrine:database:create --if-not-exists
$BASEDIR/console doctrine:schema:update -f
$BASEDIR/console app:create-client ${AUTH_CLIENT_ID} \
    --random-id=${AUTH_CLIENT_RANDOM_ID} \
    --secret=${AUTH_CLIENT_SECRET} \
    --grant-type password \
    --grant-type authorization_code \
    --redirect-uri ${AUTH_BASE_URL} \
    --redirect-uri ${UPLOADER_BASE_URL} \
    --redirect-uri ${EXPOSE_BASE_URL}
$BASEDIR/console app:create-client ${UPLOADER_CLIENT_ID} \
    --random-id=${UPLOADER_CLIENT_RANDOM_ID} \
    --secret=${UPLOADER_CLIENT_SECRET} \
    --grant-type password \
    --grant-type authorization_code
$BASEDIR/console app:create-client ${EXPOSE_CLIENT_ID} \
    --random-id=${EXPOSE_CLIENT_RANDOM_ID} \
    --secret=${EXPOSE_CLIENT_SECRET} \
    --grant-type client_credentials \
    --grant-type authorization_code
$BASEDIR/console app:user:create \
    --update-if-exist ${DEFAULT_USER_EMAIL} \
    -p ${DEFAULT_USER_PASSWORD} \
    --roles ROLE_SUPER_ADMIN
