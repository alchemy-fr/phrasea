#!/bin/sh

if [ "${APP_ENV}" == "prod" ]; then
    su app /bin/sh -c "php -d memory_limit=1G bin/console cache:clear"
fi
