#!/bin/bash

. bin/functions.sh
load-env

set -e

echo "# Resetting DB with fixtures..."

export PHP_MEMORY_LIMIT=1G

docker compose run --rm databox-api-php /bin/ash -c 'bin/console doctrine:database:drop --force \
&& bin/console doctrine:database:create \
&& bin/console doctrine:schema:create \
&& bin/console doctrine:migrations:sync-metadata-storage \
&& bin/console doctrine:migrations:version --delete --all --no-interaction \
&& bin/console doctrine:migrations:version --add --all --no-interaction \
&& bin/console hautelook:fixtures:load --no-interaction \
&& bin/console fos:elastica:populate'

docker compose run --rm -e DATABOX_UPLOADER_TARGET_SLUG uploader-api-php /bin/ash -c 'bin/console doctrine:database:drop --force \
&& bin/console doctrine:database:create \
&& bin/console doctrine:schema:create \
&& bin/console doctrine:migrations:sync-metadata-storage \
&& bin/console doctrine:migrations:version --delete --all --no-interaction \
&& bin/console doctrine:migrations:version --add --all --no-interaction \
&& bin/console app:create-target ${DATABOX_UPLOADER_TARGET_SLUG} Databox\ Uploader http://databox-api/incoming-uploads'
