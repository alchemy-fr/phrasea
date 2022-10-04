#!/bin/bash

set -e

. "bin/functions.sh"

load-env

DATE=$(date +"%Y-%m-%d-%H-%M")
DIR="./tmp/backup/postgre/${DATE}"

mkdir -p "${DIR}"

. "bin/ops/db/db.sh"

for d in ${DATABASES}; do
  DUMP_FILE="${DIR}/${d}.tar"
  echo $DUMP_FILE
  exec_container db "pg_dump -U ${POSTGRES_USER} -F c --create ${d}" > ${DUMP_FILE}
done
