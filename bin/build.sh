#!/bin/bash

set -e

$(dirname $0)/update-libs.sh

# Force build order
docker-compose -f docker-compose.yml build uploader_api_php
docker-compose -f docker-compose.yml build uploader_worker
docker-compose -f docker-compose.yml build uploader_api_nginx
docker-compose -f docker-compose.yml build uploader_client
docker-compose -f docker-compose.yml build auth_api_php
docker-compose -f docker-compose.yml build auth_worker
docker-compose -f docker-compose.yml build auth_api_nginx
docker-compose -f docker-compose.yml build redis
docker-compose -f docker-compose.yml build db
docker-compose -f docker-compose.yml build rabbitmq
docker-compose -f docker-compose.yml build dockerize
docker-compose -f docker-compose.yml build expose_api_php
docker-compose -f docker-compose.yml build expose_worker
docker-compose -f docker-compose.yml build expose_api_nginx
docker-compose -f docker-compose.yml build expose_front
docker-compose -f docker-compose.yml build notify_api_php
docker-compose -f docker-compose.yml build notify_worker
docker-compose -f docker-compose.yml build notify_api_nginx
docker-compose -f docker-compose.yml build minio
docker-compose -f docker-compose.yml build minio_mc
docker-compose -f docker-compose.yml build weblate
