#!/bin/sh

COMPLETE_FILE="/etc/nginx/tpl/frame-ancestors.ok"
if [ -d "$(dirname "${COMPLETE_FILE}")" ]; then
  if [ ! -f "${COMPLETE_FILE}" ]; then
    touch "${COMPLETE_FILE}"
    sed -i "s/frame-ancestors 'self'/frame-ancestors 'self' https:/" /etc/nginx/tpl/default.conf
  fi
fi
