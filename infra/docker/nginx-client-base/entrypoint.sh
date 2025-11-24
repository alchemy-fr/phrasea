#!/bin/sh

if [ -d /docker/entrypoint.d ]; then
  for i in /docker/entrypoint.d/*.sh; do
    if [ -r $i ]; then
      . $i
    fi
  done
  unset i
fi

envsubst '${DASHBOARD_CLIENT_URL},${ALLOWED_FRAME_ANCESTORS},${KEYCLOAK_URL}' < /etc/nginx/tpl/default.conf > /etc/nginx/conf.d/default.conf

exec "$@"
