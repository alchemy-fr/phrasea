#!/bin/sh

set -x

if [ ${XDEBUG_ENABLED} == "1" ]; then
    docker-php-ext-enable xdebug
fi

exec "$@"
