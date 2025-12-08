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

for service in databox expose uploader; do
  echo "## Resetting database for ${service}..."
  docker compose run --rm ${service}-api-php /bin/ash -c 'bin/console doctrine:database:drop --force; bin/console doctrine:database:create'
done
