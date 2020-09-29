#!/bin/sh

set -e

BASEDIR=$(dirname $0)

$BASEDIR/console doctrine:database:create --if-not-exists
echo y | $BASEDIR/console doctrine:migrations:migrate
