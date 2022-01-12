#!/bin/bash

set -e

. bin/vars.sh

for f in ${SYMFONY_PROJECTS}; do
    rm -rf "${f}/__lib"
    mkdir -p "${f}/__lib"

    if hash rsync 2>/dev/null; then
        rsync -a lib/ "${f}/__lib/" --exclude=vendor
    else
        cp -r lib/* "${f}/__lib/"
    fi

    echo "$f Synced."
done
