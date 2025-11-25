#!/bin/bash

. bin/functions.sh

load-env

docker compose up -d novu-bridge
docker compose exec novu-bridge pnpm sync
