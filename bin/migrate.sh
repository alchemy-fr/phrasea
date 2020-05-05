#!/bin/bash

set -e

BASEDIR=$(dirname $0)
. "$BASEDIR/functions.sh"
cd "$BASEDIR/.."

load-env

"$BASEDIR/update-config.sh"

set -ex

d-c up -d

# Wait for services to be ready
d-c run --rm dockerize

exec_container auth-api-php "echo y | bin/console doctrine:migrations:migrate"
