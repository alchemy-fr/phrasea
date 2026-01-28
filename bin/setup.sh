#!/bin/bash

set -e

OUTPUT_FILE=./tmp/configurator-output
mkdir -p $(dirname $OUTPUT_FILE)
rm -f $OUTPUT_FILE
touch $OUTPUT_FILE

. bin/functions.sh

load-env

set -ex

export COMPOSE_PROFILES="configurator,dashboard,databox,db,elasticsearch,expose,minio,rabbitmq,redis,report,setup,uploader"

docker compose up -d traefik keycloak minio rabbitmq db novu-api mongodb redis elasticsearch

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

if [ ! -f .env.local ]; then
    touch .env.local
fi

echo "" >> .env.local
echo "## Automatically added by configurator:" >> .env.local
cat $OUTPUT_FILE >> .env.local
echo "## End" >> .env.local

docker compose up -d novu-api novu-worker

run_container novu-bridge "pnpm sync" &
pids+=($!)

for pid in "${pids[@]}"; do
    wait "$pid"
done

docker compose up -d

echo "Done."
