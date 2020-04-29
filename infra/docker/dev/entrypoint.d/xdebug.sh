#!/bin/sh

if [ "${XDEBUG_ENABLED}" == "1" ]; then
    echo "XDEBUG is enabled. YOU MAY KEEP THIS FEATURE DISABLED IN PRODUCTION."
    docker-php-ext-enable --ini-name 20-xdebug.ini xdebug
fi
