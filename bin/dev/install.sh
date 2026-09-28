#!/bin/bash

set -e

. bin/vars.sh

pnpm install

if ! bin/dev/sf-all.sh rm -rf var/cache/test var/cache/dev; then
  echo "Retrying composer install with sudo..."
  sudo bin/dev/sf-all.sh rm -rf var/cache/test var/cache/dev
fi

bin/dev/sf-all.sh composer install
bin/dev/sf-all.sh bin/console doctrine:migrations:migrate --no-interaction

(cd databox/api && bin/console fos:elastica:populate)
