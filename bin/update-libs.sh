#!/bin/bash

set -e

BASEDIR=$(dirname $0)/..

SYMFONY_PROJECTS="
expose/api
uploader/api
"

for f in ${SYMFONY_PROJECTS}; do
    rm -rf "${BASEDIR}/${f}/__bundles"
    mkdir -p "${BASEDIR}/${f}/__bundles"
    cp -r ${BASEDIR}/bundles/* "${BASEDIR}/${f}/__bundles"
done
