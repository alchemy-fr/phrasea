#!/bin/sh

set -ex
echo 'OK'

/var/app/configurator/get-config.sh

#/var/docker/generate-env ./
nginx -g 'daemon off;'
