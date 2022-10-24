#!/bin/sh

envsubst < ./docker/app/conf.d/symfony.ini > "$PHP_INI_DIR/conf.d/symfony.ini"

if [ "${XDEBUG_ENABLED}" == "1" ]; then
    docker-php-ext-enable xdebug
fi
if [ "${NEWRELIC_ENABLED}" == "1" ]; then
    envsubst < ./docker/app/conf.d/newrelic.ini > "$PHP_INI_DIR/conf.d/newrelic.ini"
fi

if [ "${APP_ENV}" == "prod" ]; then
    su app /bin/sh -c "bin/console cache:clear"
fi

exec "$@"
