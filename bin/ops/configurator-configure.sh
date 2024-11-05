#!/bin/bash

set -e

function echo_usage() {
    echo "Usage:"
    echo "  $0 <namespace> <helm-chart-version>"
}

if [ -z "$1" ]; then
  echo "Missing Kubernetes namespace."
  echo_usage
  exit 1
fi

if [ -z "$2" ]; then
  echo "Missing HELM Chart version."
  echo_usage
  exit 1
fi

NS="${1}"
RELEASE_NAME=phrasea
CHART_VERSION="${2}"

echo "Running bin/console configure..."

(
  mkdir -p /tmp/phrasea-helm-configure \
  && cd /tmp/phrasea-helm-configure \
  && helm pull https://github.com/alchemy-fr/alchemy-helm-charts-repo/releases/download/phrasea-${CHART_VERSION}/phrasea-${CHART_VERSION}.tgz \
  && helm -n ${NS} get values ${RELEASE_NAME} -o yaml > .current-values.yaml \
  && (kubectl -n ${NS} delete job configurator-configure || true) \
  && helm template ${RELEASE_NAME} ./phrasea-${CHART_VERSION}.tgz -f .current-values.yaml \
    -s templates/configurator/configure-job.yaml | kubectl -n ${NS} apply -f -
)
