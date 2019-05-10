#!/bin/bash

set -e

export APP_ENV=prod

docker-compose -f docker-compose.yml up -d
