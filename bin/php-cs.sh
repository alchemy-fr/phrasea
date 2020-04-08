#!/bin/sh

set -e

BASEDIR=$(dirname $0)/..

. "${BASEDIR}/bin/vars.sh"

for f in ${SYMFONY_PROJECTS}; do
    echo "Fix CS in ${f}:"
    (cd "${BASEDIR}/${f}" && ./vendor/bin/php-cs-fixer fix)
done

for f in ${PHP_LIBS}; do
    echo "Fix CS in ${f}:"
    (cd "${BASEDIR}/${f}" && composer install && ./vendor/bin/php-cs-fixer fix)
done
