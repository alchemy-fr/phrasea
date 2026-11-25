#!/bin/bash
#
# Run one test tier of the stack. Everything runs in Docker (see CLAUDE.md).
#
#   bin/test.sh quick      static checks + unit tests, no service started
#                          (composer test:quick per API and lib, pnpm test:quick)
#   bin/test.sh standard [PART]
#                          the full PHP suites inside the API images, with
#                          db/redis/elasticsearch/minio up (default; what CI
#                          runs on every pull request to master). PART splits
#                          it between CI jobs, the parts together run `all`:
#                            all (default)            everything, one after the other
#                            others                   databox lint + unit suite,
#                                                     expose, uploader, configurator
#                                                     and the PHP libs
#                            databox-functional:I/N   shard I of N of the databox
#                                                     functional suite (bin/phpunit-shard)
#   bin/test.sh release    standard + Doctrine migrations replay + indexer e2e
#                          (needs the whole stack up: bin/setup.sh first)
#
# Cypress is not part of this script: see bin/dev/test-cypress.sh.
# The three tiers are documented in doc/tech/Development/ci.md.

. bin/functions.sh

load-env

. bin/vars.sh

usage() {
  echo "Usage: bin/test.sh [quick|standard [all|others|databox-functional:I/N]|release]" >&2
  exit 2
}

TIER="${1:-standard}"
PART="${2:-all}"
case "${TIER}" in
  quick|release) [ $# -le 1 ] || usage ;;
  standard)
    [ $# -le 2 ] || usage
    case "${PART}" in
      all|others) ;;
      databox-functional:*)
        DATABOX_SHARD="${PART#databox-functional:}"
        [[ "${DATABOX_SHARD}" =~ ^[1-9][0-9]*/[1-9][0-9]*$ ]] || usage
        ;;
      *) usage ;;
    esac
    ;;
  *) usage ;;
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
  export COMPOSE_PROFILES=db,redis,elasticsearch,minio,rabbitmq
  if [ -n "${REPORT_API_URL}" ]; then
    COMPOSE_PROFILES="${COMPOSE_PROFILES},report"
  fi

  docker compose up -d $(compose_up_services)
  docker compose run --rm dockerize
  setup_elasticsearch_users
  setup_minio_users

  case "${PART}" in
    all)
      for s in ${SF_SERVICES}; do
        APP_ENV=test docker compose run --rm -T ${s} su app -c "composer install --no-interaction && composer test"
      done
      APP_ENV=test docker compose run --rm -T --no-deps configurator su app -c "composer install --no-interaction && composer test"

      run_php_libs test
      ;;
    others)
      # databox `composer test` minus its functional suite, played by the
      # databox-functional shards.
      APP_ENV=test docker compose run --rm -T databox-api-php su app -c "composer install --no-interaction && composer lint && composer phpunit:unit"
      for s in ${SF_SERVICES}; do
        [ "${s}" = databox-api-php ] && continue
        APP_ENV=test docker compose run --rm -T ${s} su app -c "composer install --no-interaction && composer test"
      done
      APP_ENV=test docker compose run --rm -T --no-deps configurator su app -c "composer install --no-interaction && composer test"

      run_php_libs test
      ;;
    databox-functional:*)
      APP_ENV=test docker compose run --rm -T databox-api-php su app -c "composer install --no-interaction && composer pre-phpunit && php -d memory_limit=1024M bin/phpunit-shard ${DATABOX_SHARD}"
      ;;
  esac
}

tier_release() {
  tier_standard

  local status=0

  set +e
  bin/dev/test-migrations.sh || status=1
  # The indexer logs in through Keycloak.
  if [ "${CONFIGURATOR_CONFIGURE_KEYCLOAK}" != "false" ]; then
    bin/dev/test-indexer-e2e.sh --timeout 300 || status=1
  fi
  set -e

  return ${status}
}

tier_${TIER}
