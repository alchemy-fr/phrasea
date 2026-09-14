#!/bin/bash

set -e

. bin/functions.sh

load-env

export COMPOSE_PROFILES="dashboard,databox,db,elasticsearch,expose,minio,rabbitmq,redis,report,uploader"

pids=()
containers=()

# Force-remove the containers started by setup_container: killing the
# "docker compose run" client is not enough, the container keeps running.
remove_containers() {
    local name
    for name in "${containers[@]}"; do
        docker rm -f "${name}" >/dev/null 2>&1 || true
    done
}

cleanup() {
    # A second Ctrl+C gives up on the cleanup instead of being ignored.
    trap 'echo "Forced exit."; exit 130' INT TERM

    echo
    echo "Interrupted, stopping setup tasks..."

    local pid
    for pid in "${pids[@]}"; do
        kill -TERM "${pid}" 2>/dev/null || true
    done

    remove_containers

    wait 2>/dev/null || true

    exit 130
}

trap cleanup INT TERM

# Run a command in a one-off container, in the background, and register both the
# client pid and the container name so that they can be cleaned up on interrupt.
# Usage: setup_container <service> <command>
setup_container() {
    local service="$1"
    local command="$2"
    local name="phrasea-setup-$$-${service}"

    containers+=("${name}")
    docker compose run --rm -T --name "${name}" "${service}" su app -c "${command}" &
    pids+=($!)
}

# Wait for every registered background job, returning non-zero if any failed.
wait_all() {
    local status=0
    local pid
    for pid in "${pids[@]}"; do
        wait "${pid}" || status=$?
    done
    pids=()

    return ${status}
}

fail() {
    echo "Setup failed." >&2
    remove_containers
    exit 1
}

docker compose up -d traefik keycloak minio rabbitmq db redis elasticsearch

setup_container configurator "bin/setup.sh $@"
wait_all || fail

setup_container uploader-api-php "bin/setup.sh"
setup_container expose-api-php "bin/setup.sh"
setup_container databox-api-php "bin/setup.sh"
wait_all || fail

docker compose up -d

echo "Done."
