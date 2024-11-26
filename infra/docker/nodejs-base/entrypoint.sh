#!/bin/sh

set -ex

if [ -d /docker/entrypoint.d ]; then
  for i in /docker/entrypoint.d/*.sh; do
    if [ -r $i ]; then
      . $i
    fi
  done
  unset i
fi

exec "$@"
