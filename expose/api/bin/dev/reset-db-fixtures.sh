#!/bin/sh

set -e

echo "# Reseting DB with fixtures..."

bin/console doctrine:database:drop --force
bin/console doctrine:database:create
bin/console doctrine:schema:create
bin/console hautelook:fixtures:load --no-interaction
bin/console doctrine:migrations:sync-metadata-storage
bin/console doctrine:migrations:version --add --all --no-interaction
