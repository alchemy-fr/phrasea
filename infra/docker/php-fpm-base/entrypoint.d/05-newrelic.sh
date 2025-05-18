#!/bin/sh

if [ "${NEWRELIC_ENABLED}" == "1" ]; then
    envsubst < ./docker/php/conf.d/newrelic.ini > "$PHP_INI_DIR/conf.d/newrelic.ini"
fi
