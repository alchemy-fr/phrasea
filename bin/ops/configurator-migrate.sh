#!/bin/bash

set -ex

function echo_usage() {
    echo "Usage:"
    echo "  $0 <namespace> <migration-name> <helm-chart-version>"
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

if [ -z "$3" ]; then
  echo "Missing HELM Chart version."
  echo_usage
  exit 1
fi

NS="${1}"
MIGRATION_NAME="${2}"
RELEASE_NAME=phrasea
CHART_VERSION="${3}"

echo "Migrating..."
(
  mkdir -p /tmp/phrasea-helm-configure \
  && cd /tmp/phrasea-helm-configure \
  && helm pull https://github.com/alchemy-fr/alchemy-helm-charts-repo/releases/download/phrasea-${CHART_VERSION}/phrasea-${CHART_VERSION}.tgz \
  && helm -n ${NS} get values ${RELEASE_NAME} -o yaml > .current-values.yaml \
  && (kubectl -n ${NS} delete job configurator-migrate-${MIGRATION_NAME} || true) \
  && helm template ${RELEASE_NAME} \
    ./phrasea-${CHART_VERSION}.tgz -f .current-values.yaml \
    --set "configurator.executeMigration=${MIGRATION_NAME}" \
    -s templates/configurator/migration-job.yaml | kubectl -n ${NS} apply -f -
)
