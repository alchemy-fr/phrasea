#!/bin/bash

set -e

ROOT_DIR="$( cd "$(dirname "$0")/.." && pwd )"
. "${ROOT_DIR}/bin/env.sh"

export APP_ENV=prod

docker-compose -f docker-compose.yml up -d
