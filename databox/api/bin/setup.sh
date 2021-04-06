#!/bin/sh

set -e

BASEDIR=$(dirname $0)

if [ ! -d "${BASEDIR}/../vendor" ]; then
  (cd "${BASEDIR}/.." && composer install)
fi

is_es_ready() {
    [ $(curl --write-out %{http_code} --silent --output /dev/null ${ELASTICSEARCH_URL}_cat/health?h=st) = 200 ]
}

wait_for_es() {
    WAIT_SLEEP=3
    WAIT_LOOPS=30
    i=0
    while ! is_es_ready; do
        i=`expr $i + 1`
        if [ $i -ge $WAIT_LOOPS ]; then
            echo "$(date) - still not ready, giving up"
            exit 1
        fi
        echo "$(date) - waiting for ES to be ready"
        sleep $WAIT_SLEEP
    done
}

wait_for_es

"${BASEDIR}/console" rabbitmq:setup-fabric
"${BASEDIR}/console" doctrine:database:create --if-not-exists
"${BASEDIR}/console" doctrine:schema:update -f
"${BASEDIR}/console" fos:elastica:create
"${BASEDIR}/console" fos:elastica:populate
echo y | "${BASEDIR}/console" doctrine:migrations:sync-metadata-storage
echo y | "${BASEDIR}/console" doctrine:migrations:version --add --all
