#!/bin/bash

CHART_DIR=$(dirname $0)

set -ex

if [ ! -d "${CHART_DIR}/charts" ]; then
    (cd "${CHART_DIR}" && helm dependency update)
fi

helm install \
    --dry-run \
    --generate-name \
    ./infra/helm/ps \
    -f ./infra/helm/sample.yaml
