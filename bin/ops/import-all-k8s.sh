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

. "bin/ops/db/db.sh"


DB_HOST="$(kubectl -n $NS get configmap postgresql-php-config -o "jsonpath={.data['POSTGRES_HOST']}")"
DB_PORT="$(kubectl -n $NS get configmap postgresql-php-config -o "jsonpath={.data['POSTGRES_PORT']}")"
DB_USER="$(kubectl -n $NS get secret postgresql-secret -o "jsonpath={.data['POSTGRES_USER']}" | base64 -d)"

POD=db-psql-import

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
        valueFrom:
          secretKeyRef:
            name: postgresql-secret
            key: POSTGRES_PASSWORD
EOF

kubectl -n $NS wait --for=condition=Ready pod/${POD}

TRUNC_SQL_FILE=/tmp/truncate-all-tables.sql

cat <<'EOF' > ${TRUNC_SQL_FILE}
DO $$ DECLARE
    r RECORD;
BEGIN
    FOR r IN (SELECT tablename FROM pg_tables WHERE schemaname = current_schema()) LOOP
        EXECUTE 'DROP TABLE IF EXISTS ' || quote_ident(r.tablename) || ' CASCADE';
    END LOOP;
END $$;
EOF

for d in ${DATABASES}; do
  DUMP_FILE="${DIR}/${d}.sql"

  if [ ! -f "${DUMP_FILE}" ]; then
    echo "File ${DUMP_FILE} does not exist, skipping..."
    continue
  fi

  dpod="$d"
  if [ "$dpod" = "upload" ]; then
    dpod="uploader"
  fi

  APP_POD=$(kubectl -n $NS get pod -l tier=${dpod}-api-php -o jsonpath="{.items[0].metadata.name}")
  DB_NAME=$(kubectl -n $NS exec ${APP_POD} -- /bin/ash -c 'echo $DB_NAME')
  CONN_ARGS="-U ${DB_USER} --host ${DB_HOST} --port ${DB_PORT} ${DB_NAME}"

  if [ "${RECREATE}" = "1" ]; then
    kubectl -n $NS exec ${POD} -- dropdb ${CONN_ARGS}
    echo "[✓] ${d} old database dropped"
    kubectl -n $NS exec ${POD} -- createdb ${CONN_ARGS}
    echo "[✓] ${d} new database created"
  fi

  kubectl -n $NS exec -i ${POD} -- psql ${CONN_ARGS} < ${TRUNC_SQL_FILE}
  kubectl -n $NS exec -i ${POD} -- psql ${CONN_ARGS} < ${DUMP_FILE}
  echo "[✓] ${d} database imported"
done

kubectl -n $NS delete pod ${POD} --force 2> /dev/null
