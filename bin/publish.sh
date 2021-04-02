#!/bin/sh

set -e

docker login -u "$DOCKERHUB_USERNAME" -p "$DOCKERHUB_TOKEN"

docker-compose \
    -f docker-compose.yml \
    -f docker-compose.prod.yml \
    -f docker-compose.report-elk.yml \
  push
