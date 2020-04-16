#!/bin/sh

BASEDIR=$(dirname $0)

$BASEDIR/console rabbitmq:setup-fabric
chown -R app: /var/data/upload
$BASEDIR/console doctrine:database:create --if-not-exists
$BASEDIR/console doctrine:schema:update -f
