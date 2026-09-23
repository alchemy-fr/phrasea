#!/bin/bash
#
# Reproduce a CI run locally, with an isolated compose project.
#
#   bin/dev/run-tests-in-ci-conditions.sh [quick|standard|release]   (default: standard)
#   bin/dev/run-tests-in-ci-conditions.sh --clean                     tear the CI stack down and stop
#
# quick    builds only the PHP API images and runs bin/test.sh quick (no stack)
# standard builds everything, sets the stack up, runs bin/test.sh standard + Cypress
# release  same as standard, then migrations replay + indexer e2e (bin/test.sh release)

set -e

TIER="standard"
CLEAN="0"
for arg in "$@"; do
  case "${arg}" in
    quick|standard|release) TIER="${arg}" ;;
    --clean) CLEAN="1" ;;
    *) echo "Usage: $0 [quick|standard|release] [--clean]" >&2; exit 2 ;;
  esac
done

export DOCKER_TAG=test

export COMPOSE_PROJECT_NAME=build
export PS_SUBNET=172.34.0.0/16
export PS_GATEWAY_IP=172.34.0.1
export TRUSTED_PROXIES=172.34.0.0/16
export PHRASEA_DOMAIN=phrasea.local
export TRAEFIK_HTTPS_PORT=4442
export TRAEFIK_HTTP_PORT=8042
export HTTPS_PORT_PREFIX=':4442'
export VERIFY_SSL=false
export COMPOSE_PROFILES=databox,expose,uploader,db,rabbitmq,redis,minio,report,mailpit,elasticsearch,dashboard
export FIXTURES_GENERATE_IMAGES=true

if [[ "${CLEAN}" == "1" ]]; then
  docker compose kill
  docker compose down --volumes --remove-orphans
  exit 0
fi

restore_env_local() {
  if [[ -f .env.local.bak ]]; then
    echo "Restoring .env.local..."
    mv .env.local.bak .env.local
  fi
}
trap restore_env_local EXIT

if [[ -f .env.local ]]; then
  echo "Disabling .env.local for tests..."
  mv .env.local .env.local.bak
fi

if [[ "${TIER}" == "quick" ]]; then
  docker compose -f docker-compose.init.yml build php-fpm-base
  docker compose build databox-api-php expose-api-php uploader-api-php configurator
  bin/test.sh quick
  exit 0
fi

bin/build.sh

docker compose kill
docker compose down --volumes --remove-orphans

bin/dev/make-cert.sh
sudo PHRASEA_DOMAIN=${PHRASEA_DOMAIN} bin/dev/append-etc-hosts.sh
bin/setup.sh test

bin/test.sh standard

bin/dev/test-cypress.sh

if [[ "${TIER}" == "release" ]]; then
  docker compose --profile indexer build databox-indexer
  bin/dev/test-migrations.sh
  bin/dev/test-indexer-e2e.sh --timeout 300
fi
