#!/bin/bash

ROOT_DIR="$( cd "$(dirname "$0")/.." && pwd )"
. "${ROOT_DIR}/bin/env.sh"

set -e

docker-compose -f docker-compose.yml build
