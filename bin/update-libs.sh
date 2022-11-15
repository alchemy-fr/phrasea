#!/bin/bash

set -e

. bin/vars.sh

for f in ${SYMFONY_PROJECTS}; do
    rm -rf "${f}/__lib"
    mkdir -p "${f}/__lib"

    if hash rsync 2>/dev/null; then
        rsync -a lib/php/ "${f}/__lib/" --exclude=vendor
    else
        cp -r lib/php/* "${f}/__lib/"
        rm -rf "${f}/__lib/*/vendor"
    fi

    echo "$f Synced."
done


for f in ${JS_PROJECTS}; do
    mkdir -p "${f}/__lib"

    if hash rsync 2>/dev/null; then
        rsync -a lib/js/ "${f}/__lib/" --exclude=node_modules --exclude=dist --exclude=.idea
        rm -rf "${f}"/__lib/*/node_modules "${f}"/__lib/*/.idea
    else
        rm -rf "${f}/__lib"
        cp -r lib/js/* "${f}/__lib/"
        rm -rf "${f}"/__lib/*/node_modules "${f}"/__lib/*/.idea
    fi

    echo "$f Synced."
done
