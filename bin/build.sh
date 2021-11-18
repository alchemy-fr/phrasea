#!/bin/bash

set -e

BASEDIR=$(dirname $0)
. "$BASEDIR/functions.sh"
cd "$BASEDIR/.."

load-env

$(dirname $0)/update-libs.sh

docker-compose \
    -f docker-compose.yml \
    -f docker-compose.prod.yml \
    -f docker-compose.report-elk.yml \
  build
