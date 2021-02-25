#!/bin/sh

set -e

BASEDIR=$(dirname $0)

if [ ! -d "${BASEDIR}/../vendor" ]; then
  (cd "${BASEDIR}/.." && composer install)
fi

"${BASEDIR}/console" fos:elastica:create
"${BASEDIR}/console" fos:elastica:populate
"${BASEDIR}/console" rabbitmq:setup-fabric
"${BASEDIR}/console" doctrine:database:create --if-not-exists
"${BASEDIR}/console" doctrine:schema:update -f
echo y | "${BASEDIR}/console" doctrine:migrations:sync-metadata-storage
echo y | "${BASEDIR}/console" doctrine:migrations:version --add --all
