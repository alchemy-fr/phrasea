#!/bin/bash

set -e

BASEDIR=$(dirname $0)/..

SYMFONY_PROJECTS="
expose/api
uploader/api
auth/api
notify/api
"

for f in ${SYMFONY_PROJECTS}; do
    rm -rf "${BASEDIR}/${f}/__lib"
    mkdir -p "${BASEDIR}/${f}/__lib"
    cp -r ${BASEDIR}/lib/* "${BASEDIR}/${f}/__lib"
done
