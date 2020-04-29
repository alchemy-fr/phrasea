#!/bin/sh

envsubst < /bootstrap/symfony.ini > "$PHP_INI_DIR/conf.d/symfony.ini"
