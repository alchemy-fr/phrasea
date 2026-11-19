#!/bin/bash

# Start the dev stack with the Messenger messages sent to RabbitMQ and consumed
# by the *-worker services, as in production, instead of the sync:// transport.
# A later plain `docker compose up -d` recreates the PHP containers back in sync mode.
# Usage: bin/dev/up-workers.sh [service...]

set -e

. bin/functions.sh

# Set before load-env, so that it wins over .env and .env.local.
export DEV_MESSENGER_TRANSPORT_DSN=
load-env

if ! is_dev_env; then
  >&2 echo "Not a dev stack (APP_ENV=${APP_ENV:-<empty>}): the workers already consume RabbitMQ."
  exit 1
fi

for var in DATABOX_WORKER_REPLICAS EXPOSE_WORKER_REPLICAS UPLOADER_WORKER_REPLICAS; do
  if [ -z "${!var}" ] || [ "${!var}" = "0" ]; then
    export "${var}=1"
  fi
done

if [ $# -gt 0 ]; then
  docker compose up -d "$@"
else
  docker compose up -d $(compose_up_services)
fi
