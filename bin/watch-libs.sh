#!/bin/bash

set -e

bin/update-libs.sh
echo "Watching files in ./lib..."
while inotifywait -q --exclude '^(vendor|node_modules)' -r "lib/"*; do
    bin/update-libs.sh
    echo "$1 Synced."
done
