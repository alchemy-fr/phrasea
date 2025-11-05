#!/bin/bash

set -e

. "bin/functions.sh"

load-env

DATE=$(date +"%Y-%m-%d-%H-%M")

BASE_DIR="./tmp/export/all"
DIR="${BASE_DIR}/${DATE}"

mkdir -p "${DIR}"

. "bin/ops/db/db.sh"

for d in ${DATABASES}; do
  DUMP_FILE="${DIR}/${d}.sql"
  exec_container db "pg_dump --data-only --exclude-table=oauth_client -U ${POSTGRES_USER} ${d}" > ${DUMP_FILE} 2> /dev/null
  EXPORTED="${EXPORTED} ${d}.sql"
  echo "[✓] ${d} database exported"
done

echo "Packaging export..."
PACKAGE_NAME="phrasea-${DATE}.tar.gz"
PACKAGE="$(realpath "${BASE_DIR}/${PACKAGE_NAME}")"
tar -C ${DIR} -czf ${PACKAGE} ${EXPORTED}
rm -r ${DIR}
echo "[✓] Export saved to:"
echo "  ${PACKAGE}"
