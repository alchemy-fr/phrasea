#!/bin/sh

envsubst '${PHP_FPM_PM} ${PHP_FPM_PM_MAX_CHILDREN} ${PHP_FPM_PM_START_SERVERS} ${PHP_FPM_PM_MIN_SPARE_SERVERS} ${PHP_FPM_PM_MAX_SPARE_SERVERS} ${PHP_FPM_PM_MAX_REQUESTS}' \
  < /docker/app/fpm/zz-pool.conf > /usr/local/etc/php-fpm.d/zz-pool.conf
