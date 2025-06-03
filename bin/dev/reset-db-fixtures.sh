#!/bin/bash

. bin/functions.sh
load-env

set -e

echo "# Resetting DB with fixtures..."

docker compose run --rm databox-api-php /bin/ash -c 'bin/console doctrine:database:drop --force \
&& bin/console doctrine:database:create \
&& bin/console doctrine:schema:create \
&& bin/console doctrine:migrations:sync-metadata-storage \
&& bin/console doctrine:migrations:version --delete --all --no-interaction \
&& bin/console doctrine:migrations:version --add --all --no-interaction \
&& bin/console hautelook:fixtures:load --no-interaction \
&& bin/console fos:elastica:populate'
