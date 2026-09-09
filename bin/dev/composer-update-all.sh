#!/bin/bash

. bin/vars.sh

set -e

if ! bin/dev/sf-all.sh rm -rf var/cache/test var/cache/dev; then
  echo "Retrying composer install with sudo..."
  sudo bin/dev/sf-all.sh rm -rf var/cache/test var/cache/dev
fi

for a in ${SYMFONY_PROJECTS}; do
  echo " $a:$ $@"
  (cd "$a" && ../../bin/optimize-composer-docker-cache)
done

for a in ${PHP_LIBS}; do
  echo " $a:$ $@"
  (cd "$a" && composer update)
done
