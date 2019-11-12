#!/bin/sh

docker login -u "$DOCKERHUB_USERNAME" -p "$DOCKERHUB_TOKEN"

docker-compose -f docker-compose.yml push
