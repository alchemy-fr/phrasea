#!/bin/sh

if [ "${XDEBUG_ENABLED}" == "1" ]; then
    docker-php-ext-enable xdebug
fi
