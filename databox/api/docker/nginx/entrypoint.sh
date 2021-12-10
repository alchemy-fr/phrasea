#!/bin/sh

envsubst '$UPLOAD_MAX_FILE_SIZE' < /etc/nginx/tpl/default.conf > /etc/nginx/conf.d/default.conf

exec "$@"
