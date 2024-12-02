#!/bin/sh

envsubst '${FPM_HOSTNAME},${UPLOAD_MAX_FILE_SIZE},${DASHBOARD_CLIENT_URL}' < /etc/nginx/tpl/default.conf > /etc/nginx/conf.d/default.conf

exec "$@"
