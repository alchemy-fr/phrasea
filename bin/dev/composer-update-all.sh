#!/bin/bash

. bin/vars.sh

set -e

for a in ${SYMFONY_PROJECTS}; do
  echo " $a:$ $@"
  (cd "$a" && ../..//bin/optimize-composer-docker-cache)
done

for a in ${PHP_LIBS}; do
  echo " $a:$ $@"
  (cd "$a" && composer update)
done
