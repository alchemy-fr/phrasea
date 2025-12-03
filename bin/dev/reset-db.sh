#!/bin/bash

. bin/functions.sh
load-env

set -e

echo "# Resetting DB..."

for service in databox expose uploader; do
  echo "## Resetting database for ${service}..."
  docker compose run --rm ${service}-api-php /bin/ash -c 'bin/console doctrine:database:drop --force; bin/console doctrine:database:create'
done
