#!/bin/sh

set -e

BASEDIR=$(dirname $0)

if [ ! -d "${BASEDIR}/../vendor" ]; then
  (cd "${BASEDIR}/.." && composer install)
fi

"${BASEDIR}/console" doctrine:database:create --if-not-exists
"${BASEDIR}/console" doctrine:migrations:sync-metadata-storage

"${BASEDIR}/console" configure -vvv$1

"${BASEDIR}/console" doctrine:migrations:migrate --no-interaction
