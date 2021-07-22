#!/bin/bash

set -e

$(dirname $0)/update-libs.sh

docker-compose \
    -f docker-compose.yml \
    -f docker-compose.prod.yml \
    -f docker-compose.report-elk.yml \
  build
