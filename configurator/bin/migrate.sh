#!/bin/sh

set -e

BASEDIR=$(dirname $0)

$BASEDIR/console doctrine:migrations:sync-metadata-storage
echo y | $BASEDIR/console doctrine:migrations:migrate
