#!/bin/bash

. bin/vars.sh

docker compose exec novu-bridge pnpm sync
