#!/bin/bash

set -e

function echo_usage() {
    echo "Usage:"
    echo "  $0 <namespace> <migration-name>"
}

if [ -z "$1" ]; then
  echo "Missing Kubernetes namespace."
  echo_usage
  exit 1
fi

if [ -z "$2" ]; then
  echo "Missing migration name."
  echo_usage
  exit 1
fi

NS="${1}"
MIGRATION_NAME="${2}"
RELEASE_NAME=phrasea
RELEASE_VERSION=1.0.0-rc1

echo "Migrating..."

helm -n ${NS} get values ${RELEASE_NAME} -o yaml > /tmp/.current-values.yaml

helm pull https://github.com/alchemy-fr/alchemy-helm-charts-repo/releases/download/phrasea-${RELEASE_VERSION}/phrasea-${RELEASE_VERSION}.tgz

echo "Executing migrations..."
helm -n ${NS} upgrade ${RELEASE_NAME} ./phrasea-1.0.0-rc1.tgz \
    -f /tmp/.current-values.yaml \
    --set "configurator.executeMigration=${MIGRATION_NAME}"
