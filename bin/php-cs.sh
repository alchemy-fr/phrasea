#!/bin/sh

set -e

. bin/vars.sh

for f in ${SYMFONY_PROJECTS}; do
    echo "Fix CS in ${f}:"
    (cd "${f}" && ./vendor/bin/php-cs-fixer fix)
done

for f in ${PHP_LIBS}; do
    echo "Fix CS in ${f}:"
    (cd "${f}" && composer install && ./vendor/bin/php-cs-fixer fix)
done
