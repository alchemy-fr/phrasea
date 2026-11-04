#!/bin/sh

set -e

BASEDIR=$(dirname $0)

"${BASEDIR}/console" doctrine:migrations:sync-metadata-storage

"${BASEDIR}/console" synchronize

"${BASEDIR}/console" doctrine:migrations:migrate --no-interaction
