#!/bin/bash
#
# Cypress end-to-end smoke tests against the running stack (bin/setup.sh first).
# Shared by .github/workflows/ci.yaml and bin/dev/run-tests-in-ci-conditions.sh.
#
# Usage: bin/dev/test-cypress.sh   (run from the repository root)

set -ex

. bin/functions.sh

load-env

docker compose build cypress &
docker compose up -d --wait expose-api-php --wait-timeout 200 &
wait

# The expose specs rely on the "test-pub" publication of expose/api/fixtures/Tests.yaml
docker compose exec -T expose-api-php bin/console hautelook:fixtures:load -n
docker compose restart expose-api-nginx

docker compose run --rm cypress
