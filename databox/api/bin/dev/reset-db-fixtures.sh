#!/bin/sh

set -e

echo "# Reseting DB with fixtures..."

bin/console doctrine:database:drop --force --if-exists
bin/console doctrine:database:create
bin/console app:database:configure
bin/console doctrine:schema:create
php -d memory_limit=2G bin/console hautelook:fixtures:load --no-interaction
bin/console doctrine:migrations:sync-metadata-storage
bin/console doctrine:migrations:version --add --all --no-interaction
bin/console fos:elastica:populate
