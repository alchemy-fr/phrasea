#!/bin/bash

set -e

function echo_usage() {
    echo "Usage:"
    echo "  $0 <namespace>"
}

if [ -z "$1" ]; then
  echo "Missing Kubernetes namespace."
  echo_usage
  exit 1
fi

NS="${1}"

DATE=$(date +"%Y-%m-%d-%H-%M")

BASE_DIR="./tmp/export/all"
DIR="${BASE_DIR}/${DATE}"

mkdir -p "${DIR}"

. "bin/ops/db/db.sh"

EXPORTED=""

DB_HOST="$(kubectl -n $NS get configmap postgresql-php-config -o "jsonpath={.data['POSTGRES_HOST']}")"
DB_PORT="$(kubectl -n $NS get configmap postgresql-php-config -o "jsonpath={.data['POSTGRES_PORT']}")"
DB_USER="$(kubectl -n $NS get secret postgresql-secret -o "jsonpath={.data['POSTGRES_USER']}" | base64 -d)"
DB_PASSWORD="$(kubectl -n $NS get secret postgresql-secret -o "jsonpath={.data['POSTGRES_PASSWORD']}" | base64 -d)"

POD=db-psql-dump

kubectl -n $NS delete pod ${POD} || true

cat <<EOF | kubectl -n $NS apply -f -
apiVersion: v1
kind: Pod
metadata:
  name: ${POD}
spec:
  containers:
  - name: postgresql-client
    image: postgres:14.4-alpine
    command: [ "/bin/sh", "-c", "--" ]
    args: [ "while true; do sleep 10; done;" ]
    env:
      - name: PGPASSWORD
        value: "${DB_PASSWORD}"
EOF

kubectl -n $NS wait --for=condition=Ready pod/${POD}

for d in ${DATABASES}; do
  DUMP_FILE="${DIR}/${d}.sql"
  APP_POD=$(kubectl -n $NS get pod -l tier=${d}-api-php -o jsonpath="{.items[0].metadata.name}")
  DB_NAME=$(kubectl -n $NS exec ${APP_POD} -- /bin/ash -c 'echo $DB_NAME')

  kubectl -n $NS exec -i ${POD} -- pg_dump --data-only -U ${DB_USER} --host ${DB_HOST} --port ${DB_PORT} ${DB_NAME} > ${DUMP_FILE} 2> /dev/null
  EXPORTED="${EXPORTED} ${d}.sql"
  echo "[✓] ${d} database exported"
done

kubectl -n $NS delete pod ${POD} --force 2> /dev/null

echo "Packaging export..."
PACKAGE_NAME="phrasea-${DATE}.tar.gz"
PACKAGE="$(realpath "${BASE_DIR}/${PACKAGE_NAME}")"
tar -C ${DIR} -czf ${PACKAGE} ${EXPORTED}
rm -r ${DIR}
echo "[✓] Export saved to:"
echo "  ${PACKAGE}"
