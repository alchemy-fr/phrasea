#!/bin/bash
#
# Upgrade the stack to the images of the current DOCKER_TAG (see
# doc/tech/Deployment/lifecycle.md). Running it on an up-to-date stack changes nothing.

set -e

. bin/functions.sh

load-env

APPS=(uploader-api-php expose-api-php databox-api-php)

fail() {
    echo "Migration failed." >&2
    exit 1
}

docker compose up -d $(infra_services)

# One-off containers of the new images, while the old version may still run.
run_phase pre-upgrade configurator || fail
run_phase pre-upgrade "${APPS[@]}" || fail

docker compose up -d $(compose_up_services)

run_phase post-upgrade configurator || fail
run_phase post-upgrade "${APPS[@]}" || fail

echo "Done."
