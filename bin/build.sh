#!/bin/bash

set -e

. "bin/functions.sh"

load-env

$(dirname $0)/update-libs.sh

docker compose -f docker-compose.init.yml build
docker compose build
