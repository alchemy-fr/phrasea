#!/bin/sh

set -ex

/var/app//configurator/get-config.sh

echo 'OK'

#/var/docker/generate-env ./
nginx -g 'daemon off;'
