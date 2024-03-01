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

CONFIG_FILE="${DIR}/config.json"
if [ ! -f "${CONFIG_FILE}" ]; then
  echo "File ${CONFIG_FILE} does not exist"
  exit 2
fi
cp "${CONFIG_FILE}" ./configs/config.json
echo "[✓] config.json copied"

. "bin/ops/db/db.sh"

for d in ${DATABASES}; do
  DUMP_FILE="${DIR}/${d}.sql"

  if [ ! -f "${DUMP_FILE}" ]; then
    if [ "${d}" == "auth" ]; then
      continue
    fi

    echo "File ${DUMP_FILE} does not exist"
    exit 2
  fi
  exec_container db "psql -U ${POSTGRES_USER} -d ${d}" < ${DUMP_FILE}

  echo "[✓] ${d} database imported"
done

echo "Complete."
