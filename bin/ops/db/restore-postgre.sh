#!/bin/bash

set -e

. "bin/functions.sh"

load-env

DIR="$1"

. "bin/ops/db/db.sh"

for d in ${DATABASES}; do
  DUMP_FILE="${DIR}/${d}.tar"

  if [ ! -f "$DUMP_FILE" ]; then
    echo "File ${DUMP_FILE} does not exist"
    exit 2
  fi

  echo $DUMP_FILE
  exec_container db "pg_restore --clean -U ${POSTGRES_USER} -d ${d}" < ${DUMP_FILE}
done
