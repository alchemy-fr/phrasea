#!/bin/bash
#
# Cypress end-to-end smoke tests against the running stack (bin/setup.sh first).
# Shared by .github/workflows/ci.yaml and bin/dev/run-tests-in-ci-conditions.sh.
#
# Usage: bin/dev/test-cypress.sh [--shard I/N] [--list]   (run from the repository root)
#   --shard I/N   only shard I of N: the spec files, sorted, are dealt
#                 round-robin (spec k, from 0, goes to shard k % N + 1), so
#                 that CI jobs can share them; a new spec is picked up without
#                 any list to maintain. Default: every spec.
#   --list        print the specs that would run, and start nothing

set -e

usage() {
    echo "Usage: bin/dev/test-cypress.sh [--shard I/N] [--list]" >&2
    exit 2
}

SHARD=""
LIST="0"
while [ $# -gt 0 ]; do
    case "$1" in
        --shard)
            [[ "${2:-}" =~ ^([1-9][0-9]*)/([1-9][0-9]*)$ ]] || usage
            [ "${BASH_REMATCH[1]}" -le "${BASH_REMATCH[2]}" ] || usage
            SHARD="$2"
            shift 2
            ;;
        --list) LIST="1"; shift ;;
        *) usage ;;
    esac
done

# Paths relative to /cypress, the working directory of the cypress image.
specs=$(cd cypress && find cypress/e2e -name '*.cy.js' | LC_ALL=C sort)
if [ -n "${SHARD}" ]; then
    specs=$(awk -v i="${SHARD%/*}" -v n="${SHARD#*/}" '(NR - 1) % n == i - 1' <<< "${specs}")
fi

if [ "${LIST}" = "1" ]; then
    [ -z "${specs}" ] || echo "${specs}"
    exit 0
fi

if [ -z "${specs}" ]; then
    echo "No spec in shard ${SHARD}."
    exit 0
fi

echo "Specs:"
echo "${specs}"

set -x

. bin/functions.sh

load-env

pids=()
docker compose build cypress & pids+=($!)
docker compose up -d --wait expose-api-php --wait-timeout 200 & pids+=($!)
docker compose up -d --wait databox-client databox-api-php sockudo --wait-timeout 200 & pids+=($!)
docker compose up -d --wait uploader-client uploader-api-nginx --wait-timeout 200 & pids+=($!)
# A bare `wait` always returns 0: wait for each job to propagate its failure.
for pid in "${pids[@]}"; do
    wait "$pid"
done

# The expose specs rely on the "test-pub" publication of expose/api/fixtures/Tests.yaml
if grep -q '/expose/' <<< "${specs}"; then
    docker compose exec -T expose-api-php su app -c "bin/console hautelook:fixtures:load -n"
    docker compose restart expose-api-nginx
fi

if [ -n "${SHARD}" ]; then
    docker compose run --rm cypress --spec "$(paste -sd, - <<< "${specs}")"
else
    docker compose run --rm cypress
fi
