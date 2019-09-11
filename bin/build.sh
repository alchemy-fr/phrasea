#!/bin/bash

set -e

$(dirname $0)/update-libs.sh

docker-compose -f docker-compose.yml build
