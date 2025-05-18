#!/bin/sh

envsubst < /docker/app/conf.d/symfony.ini > "$PHP_INI_DIR/conf.d/symfony.ini"
