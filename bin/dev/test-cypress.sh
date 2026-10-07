#!/bin/bash
#
# Cypress end-to-end smoke tests against the running stack (bin/setup.sh first).
# Shared by .github/workflows/ci.yaml and bin/dev/run-tests-in-ci-conditions.sh.
#
# Usage: bin/dev/test-cypress.sh   (run from the repository root)

set -ex

. bin/functions.sh

load-env

# The cypress image is built with `network: host`. Compose drives Bake with
# --progress=rawjson and cannot grant it the network.host entitlement, which
# buildx >= 0.37.2 then rejects: use the Compose builder instead.
export COMPOSE_BAKE=false

docker compose build cypress &
docker compose up -d --wait expose-api-php --wait-timeout 200 &
docker compose up -d --wait databox-client databox-api-php soketi --wait-timeout 200 &
wait

# The expose specs rely on the "test-pub" publication of expose/api/fixtures/Tests.yaml
docker compose exec -T expose-api-php bin/console hautelook:fixtures:load -n
docker compose restart expose-api-nginx

docker compose run --rm cypress
