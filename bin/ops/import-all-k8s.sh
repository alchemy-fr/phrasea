#!/bin/bash

set -e

function echo_usage() {
    echo "Usage:"
    echo "  $0 [-r] <file> <namespace>"
    echo ""
    echo "  options:"
    echo "    -r  recreate database"
}

while getopts ":hr" option; do
  shift $(($OPTIND - 1))
  case $option in
    h) # display Help
      echo_usage
      exit;;
    r) # Enter a name
      RECREATE=1;;
    \?) # Invalid option
    echo "Error: Invalid option"
    exit;;
  esac
done

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

PACKAGE="${1}"
NS="${2}"

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

POD=db-psql-import

DB_HOST="$(kubectl -n $NS get configmap postgresql-php-config -o "jsonpath={.data['POSTGRES_HOST']}")"
DB_PORT="$(kubectl -n $NS get configmap postgresql-php-config -o "jsonpath={.data['POSTGRES_PORT']}")"
DB_USER="$(kubectl -n $NS get secret postgresql-secret -o "jsonpath={.data['POSTGRES_USER']}" | base64 -d)"
DB_PASSWORD="$(kubectl -n $NS get secret postgresql-secret -o "jsonpath={.data['POSTGRES_PASSWORD']}" | base64 -d)"

kubectl -n $NS delete pod ${POD} || true

cat <<EOF | kubectl -n $NS apply -f -
apiVersion: v1
kind: Pod
metadata:
  name: ${POD}
spec:
  containers:
  - name: postgresql-client
    image: jbergknoff/postgresql-client
    command: [ "/bin/sh", "-c", "--" ]
    args: [ "while true; do sleep 10; done;" ]
    env:
      - name: PGPASSWORD
        value: "${DB_PASSWORD}"
EOF

kubectl -n $NS wait --for=condition=Ready pod/${POD}

for d in ${DATABASES}; do
  DUMP_FILE="${DIR}/${d}.sql"

  if [ ! -f "${DUMP_FILE}" ]; then
    echo "File ${DUMP_FILE} does not exist"
    exit 2
  fi

  APP_POD=$(kubectl -n $NS get pod -l tier=${d}-api-php -o jsonpath="{.items[0].metadata.name}")
  DB_NAME=$(kubectl -n $NS exec ${APP_POD} -- /bin/ash -c 'echo $DB_NAME')
  CONN_ARGS="-U ${DB_USER} --host ${DB_HOST} --port ${DB_PORT} ${DB_NAME}"

  if [ "${RECREATE}" = "1" ]; then
    kubectl -n $NS exec ${POD} -- dropdb ${CONN_ARGS}
    echo "[✓] ${d} old database dropped"
    kubectl -n $NS exec ${POD} -- createdb ${CONN_ARGS}
    echo "[✓] ${d} new database created"
  fi
  kubectl -n $NS exec ${POD} -- psql ${CONN_ARGS} < ${DUMP_FILE}
  echo "[✓] ${d} database imported"
done

kubectl -n $NS delete pod ${POD} --force

echo "[!] config.json cannot be updated automatically, depending on your infra."
echo "Don't forget to update your ConfigMap with its content:"
echo "  $ cat $(realpath ${DIR})/config.json"
