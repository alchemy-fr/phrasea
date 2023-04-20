#!/bin/bash

set -e

. "bin/functions.sh"

load-env

DATE=$(date +"%Y-%m-%d-%H-%M")
DIR="./tmp/export/postgre/${DATE}"

mkdir -p "${DIR}"

. "bin/ops/db/db.sh"

for d in ${DATABASES}; do
  DUMP_FILE="${DIR}/${d}.sql"
  echo "Export of $DUMP_FILE..."
  exec_container db "pg_dump -U ${POSTGRES_USER} --create ${d}" > ${DUMP_FILE}
done

echo "Delete DB volumes"
docker compose stop db
docker compose rm -f db
docker volume rm ${COMPOSE_PROJECT_NAME}_db_vol
docker compose up -d db

# Wait for services to be ready
docker compose run --rm dockerize

for d in ${DATABASES}; do
  DUMP_FILE="${DIR}/${d}.sql"
  echo "Re-importing $DUMP_FILE..."
  exec_container db "psql -U ${POSTGRES_USER}" < ${DUMP_FILE}
done
