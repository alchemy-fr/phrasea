#!/bin/sh

envsubst < ./docker/app/conf.d/symfony.ini > "$PHP_INI_DIR/conf.d/symfony.ini"

mkdir -p /etc/supervisor.d

for i in ${WORKER_PRIORITIES}; do
  export WORKER_CHANNEL="${i}"
  envsubst < ./docker/worker/worker.ini > /etc/supervisor.d/worker-${WORKER_CHANNEL}.ini
done

cp ./docker/worker/unoserver.ini /etc/supervisor.d/unoserver.ini

unset WORKER_CHANNEL

if [ "${NEWRELIC_ENABLED}" == "1" ]; then
    envsubst < ./docker/php/conf.d/newrelic.ini > "$PHP_INI_DIR/conf.d/newrelic.ini"
fi

su app -c 'php -d memory_limit=1G bin/console cache:clear'

exec docker-php-entrypoint "$@"
