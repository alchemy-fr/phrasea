#!/bin/bash

set -e

function echo_usage() {
    echo "Usage:"
    echo "  $0 <namespace> <db-name>"
}

if [ -z "$1" ]; then
  echo "Missing Kubernetes namespace."
  echo_usage
  exit 1
fi

if [ -z "$2" ]; then
  echo "Missing database name."
  echo_usage
  exit 1
fi

NS="${1}"
DB_NAME="${2}"

DB_HOST="$(kubectl -n $NS get configmap postgresql-php-config -o "jsonpath={.data['POSTGRES_HOST']}")"
DB_PORT="$(kubectl -n $NS get configmap postgresql-php-config -o "jsonpath={.data['POSTGRES_PORT']}")"
DB_USER="$(kubectl -n $NS get secret postgresql-secret -o "jsonpath={.data['POSTGRES_USER']}" | base64 -d)"

POD=db-psql-client

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
kubectl -n $NS exec -ti ${POD} -- psql --username=${DB_USER} --host=${DB_HOST} --port=${DB_PORT} ${DB_USER}_${DB_NAME}

kubectl -n $NS delete pod ${POD} --force 2> /dev/null
