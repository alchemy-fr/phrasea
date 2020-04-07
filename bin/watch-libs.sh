#!/bin/bash

set -e

BASEDIR=$(dirname $0)/..

"${BASEDIR}/bin/update-libs.sh"
echo "Watching files in ./lib..."
while inotifywait -q -r "lib/"*; do
    "${BASEDIR}/bin/update-libs.sh"
    echo "$1 Synced."
done
