#!/bin/sh

set -e

BASEDIR=$(dirname $0)

if [ ! -d "${BASEDIR}/../vendor" ]; then
  (cd "${BASEDIR}/.." && composer install)
fi

"${BASEDIR}/console" rabbitmq:setup-fabric -vvv
"${BASEDIR}/console" doctrine:database:create --if-not-exists
"${BASEDIR}/console" doctrine:schema:update -f
"${BASEDIR}/console" doctrine:migrations:sync-metadata-storage
"${BASEDIR}/console" doctrine:migrations:version --add --all -n
