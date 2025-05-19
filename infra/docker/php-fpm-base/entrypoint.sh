#!/bin/sh

set -e

if [ -d /docker/entrypoint.d ]; then
  for i in /docker/entrypoint.d/*.sh; do
    if [ -r $i ]; then
      echo "[Entrypoint] > $i"
      . $i
    fi
  done
  unset i
fi

exec "$@"
