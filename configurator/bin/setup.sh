#!/bin/sh

set -e

BASEDIR=$(dirname $0)

if [ ! -d "${BASEDIR}/../vendor" ]; then
  (cd "${BASEDIR}/.." && composer install)
fi

"${BASEDIR}/console" doctrine:database:create --if-not-exists
"${BASEDIR}/console" doctrine:migrations:sync-metadata-storage

PRESETS=""
for p in $@; do
  PRESETS="${PRESETS} --preset $p"
done

"${BASEDIR}/console" configure -vvv$PRESETS

"${BASEDIR}/console" doctrine:migrations:migrate --no-interaction
