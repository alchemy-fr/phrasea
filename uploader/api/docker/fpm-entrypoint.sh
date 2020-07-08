#!/bin/sh

envsubst < ./docker/app/conf.d/symfony.ini > "$PHP_INI_DIR/conf.d/symfony.ini"

if [ "${XDEBUG_ENABLED}" == "1" ]; then
    docker-php-ext-enable xdebug
fi

# Ensure upload data directory is writable for "app" user
if [ "$(stat -c '%U' "${UPLOAD_TEMP_DIR}")" != "app" ]; then
    echo "Fixing ${UPLOAD_TEMP_DIR} permissions..."
    chown -R app:app "${UPLOAD_TEMP_DIR}"
fi

# Must refresh cache to interpret /configs/config.json
if [ "${APP_ENV}" == "prod" ]; then
    su app /bin/sh -c "bin/console cache:clear"
fi

exec "$@"
