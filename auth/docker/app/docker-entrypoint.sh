#!/bin/sh

set -e

bin/console cache:clear

exec "$@"
