#!/bin/bash
#
# Run one test tier of the stack. Everything runs in Docker (see CLAUDE.md).
#
#   bin/test.sh quick      static checks + unit tests, no service started
#                          (composer test:quick per API and lib, pnpm test:quick)
#   bin/test.sh standard   the full PHP suites inside the API images, with
#                          db/redis/elasticsearch/minio up (default; what CI
#                          runs on every pull request to master)
#   bin/test.sh release    standard + Doctrine migrations replay + indexer e2e
#                          (needs the whole stack up: bin/setup.sh first)
#
# Cypress is not part of this script: see bin/dev/test-cypress.sh.
# The three tiers are documented in doc/tech/Development/ci.md.

. bin/functions.sh

load-env

. bin/vars.sh

TIER="${1:-standard}"
case "${TIER}" in
  quick|standard|release) ;;
  *) echo "Usage: bin/test.sh [quick|standard|release]" >&2; exit 2 ;;
esac

set -ex

export XDEBUG_ENABLED=0
export VERIFY_SSL=false

# Symfony API services (docker compose service = <app>-api-php)
SF_SERVICES="
databox-api-php
expose-api-php
uploader-api-php
"

# Every lib runs `composer test` (var-dump-check, plus PHPUnit where a suite
# exists) from /lib/php/<lib> inside the databox image: the image copies
# lib/php there (and the dev override bind-mounts it), which also covers the
# libs databox does not depend on (report-sdk, report-bundle).
#
# The libs' composer.lock files are git-ignored: CI always resolves them from
# composer.json, while a local lock can be stale after a constraint bump, in
# which case `composer install` refuses to run and we resolve again.
run_php_libs() {
  local composer_script="$1"
  for lib in ${PHP_LIBS}; do
    dir=$(basename ${lib})
    echo "Testing PHP lib: ${lib}"
    APP_ENV=test docker compose run --rm -T --no-deps databox-api-php su app -c "cd /lib/php/${dir} && (composer install --no-interaction || (echo 'composer.lock is stale, resolving again' && composer update --no-interaction)) && composer ${composer_script}"
  done
}

run_js_quick() {
  if command -v pnpm >/dev/null 2>&1; then
    pnpm install --frozen-lockfile
    pnpm test:quick
  else
    docker compose run --rm -T --user=1000 --entrypoint="" dev sh -c "pnpm install --frozen-lockfile && pnpm test:quick"
  fi
}

tier_quick() {
  # No `docker compose up`: nothing here talks to a service.
  for s in ${SF_SERVICES}; do
    APP_ENV=test docker compose run --rm -T --no-deps ${s} su app -c "composer install --no-interaction && composer test:quick"
  done
  APP_ENV=test docker compose run --rm -T --no-deps configurator su app -c "composer install --no-interaction && composer test:quick"

  run_php_libs test

  run_js_quick
}

tier_standard() {
  export COMPOSE_PROFILES=db,redis,elasticsearch,report,minio,rabbitmq

  docker compose up -d
  docker compose run --rm dockerize

  for s in ${SF_SERVICES}; do
    APP_ENV=test docker compose run --rm -T ${s} su app -c "rm -rf bin/.phpunit && composer install --no-interaction && composer test"
  done
  APP_ENV=test docker compose run --rm -T --no-deps configurator su app -c "composer install --no-interaction && composer test"

  run_php_libs test
}

tier_release() {
  tier_standard

  local status=0

  set +e
  bin/dev/test-migrations.sh || status=1
  bin/dev/test-indexer-e2e.sh --timeout 300 || status=1
  set -e

  return ${status}
}

tier_${TIER}
