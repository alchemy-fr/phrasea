#!/bin/sh

BASEDIR=$(dirname $0)

if [ ! -d "${BASEDIR}/../vendor" ]; then
  (cd "${BASEDIR}/.." && composer install)
fi

max_retries=30
count=0
until nc -z $POSTGRES_HOST $POSTGRES_PORT; do
  count=$((count+1))
  if [ $count -ge $max_retries ]; then
    echo "Server did not become ready in time."
    exit 1
  fi
  echo "Waiting for database $count/$max_retries..."
  sleep 1
done

set -e

"${BASEDIR}/console" doctrine:database:create --if-not-exists
"${BASEDIR}/console" doctrine:migrations:sync-metadata-storage

PRESETS=""
for p in $@; do
  PRESETS="${PRESETS} --preset $p"
done

"${BASEDIR}/console" configure -vvv$PRESETS

"${BASEDIR}/console" doctrine:migrations:migrate --no-interaction
