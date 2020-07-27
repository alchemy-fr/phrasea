#!/bin/sh

set -ex

export ALL_ENV=$(env | sort)

envsubst < /var/app/index.tpl.html > /usr/share/nginx/html/index.html
envsubst < /var/app/menu.tpl.html > /usr/share/nginx/html/menu.html

exec "$@"
