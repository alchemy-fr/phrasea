#!/bin/bash

set -e

BASEDIR=$(dirname $0)/..

. "${BASEDIR}/bin/vars.sh"

for f in ${SYMFONY_PROJECTS}; do
    rm -rf "${BASEDIR}/${f}/__lib"
    mkdir -p "${BASEDIR}/${f}/__lib"

    if hash rsync 2>/dev/null; then
        rsync -a "${BASEDIR}/lib/" "${BASEDIR}/${f}/__lib/" --exclude=vendor
    else
        cp -r "${BASEDIR}/lib/"* "${BASEDIR}/${f}/__lib/"
    fi

    echo "$f Synced."
done
