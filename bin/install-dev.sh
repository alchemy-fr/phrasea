#!/bin/bash

BASEDIR=$(dirname $0)

. "${BASEDIR}/load.env.sh"
. "${BASEDIR}/vars.sh"

for f in ${SYMFONY_PROJECTS}; do
    echo "Install dependencies for ${f}"
    (cd "${BASEDIR}/../${f}" && composer install)
done

for f in ${NPM_PROJECTS}; do
    echo "Install dependencies for ${f}"
    (cd "${BASEDIR}/../${f}" && yarn install)
done
