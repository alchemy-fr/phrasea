#!/bin/sh

set -e

envsubst < ./docker/app/conf.d/symfony.ini > "$PHP_INI_DIR/conf.d/symfony.ini"

if [ "${XDEBUG_ENABLED}" == "1" ]; then
    docker-php-ext-enable xdebug
fi
if [ "${NEWRELIC_ENABLED}" == "1" ]; then
    envsubst < ./docker/app/conf.d/newrelic.ini > "$PHP_INI_DIR/conf.d/newrelic.ini"
fi

if [ "${APP_ENV}" == "prod" ]; then
    su app /bin/sh -c "php -d memory_limit=1G bin/console cache:clear"
fi

exec "$@"
