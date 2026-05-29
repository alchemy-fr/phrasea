#!/bin/bash

set -e

export COMPOSE_PROJECT_NAME=build
export PS_SUBNET=172.46.0.0/16
export PS_GATEWAY_IP=172.46.0.1
export PHRASEA_DOMAIN=phrasea.test
export TRAEFIK_HTTPS_PORT=4442
export TRAEFIK_HTTP_PORT=8042
export HTTPS_PORT_PREFIX=':4442'
export VERIFY_SSL=false
export COMPOSE_PROFILES=databox,expose,uploader,db,rabbitmq,redis,minio,report,mailhog,elasticsearch,dashboard
export FIXTURES_GENERATE_IMAGES=true

docker compose kill
docker compose down --volumes --remove-orphans

if [[ "$1" == "--clean" ]]; then
  exit 0
fi

if [[ -f .env.local ]]; then
  echo "Disabling .env.local for tests..."
  mv .env.local .env.local.bak
fi

docker compose build dashboard-client configurator expose-api-php expose-api-nginx expose-client

bin/dev/make-cert.sh
sudo PHRASEA_DOMAIN=${PHRASEA_DOMAIN} bin/dev/append-etc-hosts.sh
bin/setup.sh test

docker compose build cypress &
docker compose up -d --wait expose-api-php --wait-timeout 200 &
wait
docker compose exec expose-api-php bin/console hautelook:fixtures:load -n

docker compose run --rm cypress

if [[ -f .env.local.bak ]]; then
  echo "Restoring .env.local..."
  mv .env.local.bak .env.local
fi
