#!/bin/sh

set +ex

BASEDIR=$(dirname $0)

$BASEDIR/console doctrine:database:create --if-not-exists
$BASEDIR/console doctrine:schema:update -f
