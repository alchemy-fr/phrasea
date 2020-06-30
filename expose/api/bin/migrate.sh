#!/bin/sh

set -e

BASEDIR=$(dirname $0)

echo y | $BASEDIR/console doctrine:migrations:migrate
