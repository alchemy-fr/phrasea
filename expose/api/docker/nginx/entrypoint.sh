#!/bin/sh

envsubst '${UPLOAD_MAX_FILE_SIZE},${PS_SUBNET},${DASHBOARD_CLIENT_URL}' < /etc/nginx/tpl/default.conf > /etc/nginx/conf.d/default.conf

exec "$@"
