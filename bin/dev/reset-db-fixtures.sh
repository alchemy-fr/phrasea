#!/bin/bash

set -e

./bin/dev/reset-db.sh

. bin/functions.sh
load-env

export DEV_PHP_MEMORY_LIMIT=-1

echo "# Generating fixtures..."
echo "## Setting up configurator..."
docker compose run --rm configurator /bin/ash -c 'bin/setup.sh'

for service in databox expose uploader; do
  echo "## Setting up ${service}..."
  docker compose run --rm ${service}-api-php /bin/ash -c 'bin/console doctrine:schema:create \
    && bin/console doctrine:migrations:sync-metadata-storage \
    && bin/console doctrine:migrations:version --delete --all --no-interaction \
    && bin/console doctrine:migrations:version --add --all --no-interaction'
done

echo "## Setting up databox..."
docker compose run --rm databox-api-php /bin/ash -c 'bin/console hautelook:fixtures:load --no-interaction \
&& php -d memory_limit=-1 bin/console app:workspace:ingest-assets \
&& bin/console fos:elastica:populate'

echo "## Setting up expose..."
docker compose run --rm expose-api-php /bin/ash -c 'bin/console hautelook:fixtures:load --no-interaction'
