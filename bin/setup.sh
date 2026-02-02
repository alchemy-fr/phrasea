#!/bin/bash

set -e

. bin/functions.sh

load-env

set -ex

export COMPOSE_PROFILES="configurator,dashboard,databox,db,elasticsearch,expose,minio,rabbitmq,redis,report,setup,uploader"

docker compose up -d traefik keycloak minio rabbitmq db redis elasticsearch

cleanup() {
    echo "Stopping task..."
    kill $(jobs -p) 2>/dev/null
    exit 130
}

trap cleanup SIGINT SIGTERM

run_container_as configurator "bin/setup.sh $@" app

pids=()
run_container_as uploader-api-php "bin/setup.sh" app &
pids+=($!)
run_container_as expose-api-php "bin/setup.sh" app &
pids+=($!)
run_container_as databox-api-php "bin/setup.sh" app &
pids+=($!)

docker compose up -d

echo "Done."
