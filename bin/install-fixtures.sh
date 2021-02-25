#!/bin/bash

set -e

BASEDIR=$(dirname $0)
. "$BASEDIR/functions.sh"
cd "$BASEDIR/.."

load-env

set -ex

d-c up -d

# Wait for services to be ready
d-c run --rm dockerize

exec_container_as auth-api-php "bin/console hautelook:fixtures:load --no-interaction" app
exec_container_as databox-api-php "bin/console hautelook:fixtures:load --no-interaction" app

"$BASEDIR/setup.sh"
