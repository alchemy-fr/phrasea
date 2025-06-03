#!/bin/bash

set -e

. bin/functions.sh
load-env


docker compose kill redis
docker compose rm -f redis
VOL=$(docker compose config --format json | jq -r '.volumes.redis.name')
docker volume rm ${VOL}
docker compose up -d redis
