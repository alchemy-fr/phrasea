#!/bin/bash

set -e

. bin/functions.sh

load-env

set -ex

docker compose up -d traefik

cleanup() {
    echo "Stopping task..."
    kill $(jobs -p) 2>/dev/null
    exit 130
}

trap cleanup SIGINT SIGTERM

run_container_as configurator "bin/setup.sh $@" app &
last_pid=$!
wait $last_pid

pids=()
run_container_as uploader-api-php "bin/setup.sh" app &
pids+=($!)
run_container_as expose-api-php "bin/setup.sh" app &
pids+=($!)
run_container_as databox-api-php "bin/setup.sh" app &
pids+=($!)
run_container novu-bridge "pnpm sync" &
pids+=($!)

for pid in "${pids[@]}"; do
    wait "$pid"
done

docker compose up -d

echo "Done."
