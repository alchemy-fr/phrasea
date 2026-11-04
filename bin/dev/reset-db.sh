#!/bin/bash

. bin/functions.sh
load-env

set -e

echo "# Resetting DB..."

INCLUDE_KEYCLOAK=${INCLUDE_KEYCLOAK:-"0"}

if [ "${INCLUDE_KEYCLOAK}" -eq "1" ]; then
  docker compose kill keycloak
  echo "## Dropping keycloak database if exists..."
  exec_container db "psql -U ${POSTGRES_USER} -c 'DROP DATABASE IF EXISTS keycloak;'"
  echo "[✓] keycloak database dropped if existed"
  docker compose up -d keycloak
fi

# Doctrine's drop is a plain DROP DATABASE, refused by Postgres while a worker
# (scheduler, consumers, php-fpm) still holds a connection: WITH (FORCE) kills them.
for entry in "databox-api-php:${DATABOX_DB_NAME}" "expose-api-php:${EXPOSE_DB_NAME}" "uploader-api-php:${UPLOADER_DB_NAME}" "configurator:${CONFIGURATOR_DB_NAME}"; do
  service="${entry%%:*}"
  db_name="${entry#*:}"
  echo "## Resetting database for ${service}..."
  exec_container db "psql -U \"${POSTGRES_USER}\" -c 'DROP DATABASE IF EXISTS ${db_name} WITH (FORCE);'"
  docker compose run --rm ${service} /bin/ash -c 'bin/console doctrine:database:create && (!(bin/console | grep app:database:configure) || bin/console app:database:configure)'
done
