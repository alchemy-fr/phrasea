#!/bin/bash

set -e

if [ -z "$1" ]; then
  echo "Missing arg."
  echo "Usage:"
  echo "  $0 path/to/phrasea-date.tar.gz"
  exit 1
fi

PACKAGE="${1}"

if [ ! -f "${PACKAGE}" ]; then
  echo "File ${PACKAGE} does not exist."
  exit 2
fi

. "bin/functions.sh"

load-env

DATE=$(date +"%Y-%m-%d-%H-%M")
DIR="./tmp/extracts/${DATE}"

mkdir -p "${DIR}"

tar -C ${DIR} -xf ${PACKAGE}

. "bin/ops/db/db.sh"

function import_db() {
  local db_name="$1"
  echo "## Importing ${db_name} database..."
  local dump_file="${DIR}/${db_name}.sql"

  if [ ! -f "${dump_file}" ]; then
    echo "File ${dump_file} does not exist"
    exit 2
  fi

  exec_container db "psql -U ${POSTGRES_USER} -d ${db_name}" < ${dump_file}
  echo "[✓] ${db_name} database imported"
}

for d in ${DATABASES}; do
  import_db "${d}"
done

if [ -f "${DIR}/keycloak.sql" ]; then
  import_db "keycloak"
fi

echo "Complete."
