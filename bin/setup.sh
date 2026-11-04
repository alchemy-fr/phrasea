#!/bin/bash
#
# Install the stack (see doc/tech/Deployment/lifecycle.md).
#
# Usage: bin/setup.sh [preset...]   (presets of the configurator, e.g. "test" or "dev")

set -e

. bin/functions.sh

load-env

export COMPOSE_PROFILES="dashboard,databox,db,elasticsearch,expose,minio,rabbitmq,redis,uploader"
if [ -n "${REPORT_API_URL}" ]; then
    COMPOSE_PROFILES="${COMPOSE_PROFILES},report"
fi

export CONFIGURATOR_PRESETS="$*"

APPS=(uploader-api-php expose-api-php databox-api-php)

fail() {
    echo "Setup failed." >&2
    exit 1
}

docker compose up -d $(infra_services)

run_phase pre-install configurator || fail
run_phase pre-install "${APPS[@]}" || fail

docker compose up -d $(compose_up_services)

run_phase post-install configurator || fail
run_phase post-install "${APPS[@]}" || fail

echo "Done."
