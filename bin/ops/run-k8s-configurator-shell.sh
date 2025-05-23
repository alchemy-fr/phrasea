#!/bin/bash

JOB=configurator-shell-client

set -ex

function echo_usage() {
    echo "Usage:"
    echo "  $0 <namespace> <chart-version>"
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


(
  mkdir -p /tmp/phrasea-helm-configure-shell \
  && cd /tmp/phrasea-helm-configure-shell \
  && helm pull https://github.com/alchemy-fr/phrasea-helm-charts/releases/download/phrasea-${CHART_VERSION}/phrasea-${CHART_VERSION}.tgz \
  && helm -n ${NS} get values ${RELEASE_NAME} -o yaml > .current-values.yaml \
  && (kubectl -n $NS delete job ${JOB} || true) \
  && helm template ${RELEASE_NAME} \
    ./phrasea-${CHART_VERSION}.tgz -f .current-values.yaml \
    -s templates/configurator/migrate-job.yaml  \
      | sed 's=args: \["bin/migrate.sh"\]=args: [ "ash", "-c", "while true; do sleep 10; done;" ]=g' \
      | sed -r 's/name:\s+.+?-migrate/name: '"$JOB"'/g' \
      | kubectl -n ${NS} apply -f -
)

kubectl -n $NS wait --for=condition=Ready pod/${JOB}
kubectl -n $NS exec -ti ${JOB} -- ash

kubectl -n $NS delete job ${JOB} --force 2> /dev/null
