#!/bin/bash

set -e

BASEDIR=$(dirname $0)/..

. "${BASEDIR}/bin/vars.sh"

for f in ${SYMFONY_PROJECTS}; do
    rm -rf "${BASEDIR}/${f}/__lib"
    mkdir -p "${BASEDIR}/${f}/__lib"

    rsync -av "${BASEDIR}/lib/" "${BASEDIR}/${f}/__lib/" --exclude=vendor
done
