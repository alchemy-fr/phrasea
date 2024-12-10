#!/bin/bash

set -e

. bin/functions.sh
load-env

docker compose restart \
  databox-api-nginx \
  expose-api-nginx \
  uploader-api-nginx
