#!/bin/bash

set -e

function echo_usage() {
    echo "Usage:"
    echo "  $0 <file> <namespace> <db-host> <db-port> <db-user> <db-password> <database-name-prefix>"
}

if [ -z "$1" ]; then
  echo "Missing file to import."
  echo_usage
  exit 1
fi
if [ -z "$2" ]; then
  echo "Missing Kubernetes namespace."
  echo_usage
  exit 1
fi
if [ -z "$3" ]; then
  echo "Missing database host."
  echo_usage
  exit 1
fi
if [ -z "$4" ]; then
  echo "Missing database port."
  echo_usage
  exit 1
fi
if [ -z "$5" ]; then
  echo "Missing database user."
  echo_usage
  exit 1
fi
if [ -z "$6" ]; then
  echo "Missing database password."
  echo_usage
  exit 1
fi

PACKAGE="${1}"
NS="${2}"
DB_HOST="${3}"
DB_PORT="${4}"
DB_USER="${5}"
DB_PASSWORD="${6}"
DB_PREFIX="${7}"

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

. "bin/ops/db/db.sh"

for d in ${DATABASES}; do
  DUMP_FILE="${DIR}/${d}.sql"

  if [ ! -f "${DUMP_FILE}" ]; then
    echo "File ${DUMP_FILE} does not exist"
    exit 2
  fi

  kubectl -n $NS exec ${POD} -- psql -u ${DB_USER} -p${DB_PASSWORD} ${DB_PREFIX}${d} < ${DUMP_FILE}
  echo "[✓] ${d} database imported"
done

echo "[!] config.json cannot be updated automatically, depending on your infra."
echo "Don't forget to update your ConfigMap with its content:"
echo "  $ cat $(realpath ${DIR})/config.json"
