#!/bin/bash

set -e

. "bin/functions.sh"

load-env

docker compose -f docker-compose.init.yml build $@
docker compose build $@
