#!/bin/sh

set -e

if [ -d /docker/entrypoint.d ]; then
  for i in /docker/entrypoint.d/*.sh; do
    if [ -r $i ]; then
      . $i
    fi
  done
  unset i
fi

if [ ! -t 1 ] ; then
  echo "No tty available."
  exit 0
fi

exec su app -c "$*"
