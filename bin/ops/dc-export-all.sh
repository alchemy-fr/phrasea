#!/bin/bash

set -e

. "bin/functions.sh"

load-env

DATE=$(date +"%Y-%m-%d-%H-%M")

BASE_DIR="./tmp/export/all"
DIR="${BASE_DIR}/${DATE}"

mkdir -p "${DIR}"

. "bin/ops/db/db.sh"

cp configs/config.json "${DIR}/config.json"
EXPORTED="config.json"

for d in ${DATABASES}; do
  DUMP_FILE="${DIR}/${d}.sql"
  echo $DUMP_FILE
  exec_container db "pg_dump -U ${POSTGRES_USER} --create ${d}" > ${DUMP_FILE}
  EXPORTED="${EXPORTED} ${d}.sql"
done

echo "Packaging export..."
PACKAGE_NAME="phrasea-${DATE}.tar.gz"
PACKAGE="${BASE_DIR}/${PACKAGE_NAME}"
tar -C ${DIR} -czf ${PACKAGE} ${EXPORTED}
rm -r ${DIR}
echo "[Done] Export saved to ${PACKAGE}."
