#!/bin/bash

set -e

. bin/functions.sh
load-env

docker compose restart \
  auth-api-nginx \
  databox-api-nginx \
  expose-api-nginx \
  notify-api-nginx \
  uploader-api-nginx
