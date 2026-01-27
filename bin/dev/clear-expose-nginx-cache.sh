#!/bin/bash

set -e

. bin/functions.sh
load-env

SVC=expose-api-nginx

docker compose kill $SVC
docker compose rm -f $SVC
VOL=$(docker compose config --format json | jq -r '.volumes.expose_nginx_cache.name')
docker volume rm ${VOL}
docker compose up -d $SVC
