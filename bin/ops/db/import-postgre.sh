#!/bin/bash

# Import database dumped with bin/ops/export-postgre.sh

set -e

. "bin/functions.sh"

load-env

DIR="$1"

if [ ! -d "$1" ]; then
  echo "Directory $1 does not exist"
  exit 1
fi

. "bin/ops/db/db.sh"

for d in ${DATABASES}; do
  DUMP_FILE="${DIR}/${d}.sql"
  echo $DUMP_FILE

  if [ ! -f "$DUMP_FILE" ]; then
    echo "File ${$DUMP_FILE} does not exist"
    exit 2
  fi
  exec_container db "dropdb -U ${POSTGRES_USER} ${d}"
  exec_container db "createdb -U ${POSTGRES_USER} ${d}"
  exec_container db "psql -U ${POSTGRES_USER} -d ${d}" < ${DUMP_FILE}
done
