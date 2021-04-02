#!/bin/bash

set -e

$(dirname $0)/update-libs.sh

# Force build order
docker-compose build uploader-api-php
docker-compose build uploader-worker
docker-compose build uploader-api-nginx
docker-compose build uploader-client
docker-compose build auth-api-php
docker-compose build auth-worker
docker-compose build auth-api-nginx
docker-compose build redis
docker-compose build db
docker-compose build rabbitmq
docker-compose build dockerize
docker-compose build expose-api-php
docker-compose build expose-worker
docker-compose build expose-api-nginx
docker-compose build expose-client
docker-compose build notify-api-php
docker-compose build notify-worker
docker-compose build notify-api-nginx
docker-compose build databox-api-php
docker-compose build databox-worker
docker-compose build databox-api-nginx
docker-compose build databox-client
docker-compose build minio
docker-compose build minio-mc
docker-compose build report-api
docker-compose build dashboard
docker-compose -f docker-compose.yml -f docker-compose.report-elk.yml build report-logstash
