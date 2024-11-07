#!/bin/bash

set -e

. "bin/functions.sh"

load-env

bin/git-log.sh

docker compose -f docker-compose.init.yml build $@
docker compose build $@
