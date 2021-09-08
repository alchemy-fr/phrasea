#!/bin/bash

BASEDIR=$(dirname $0)

apps=(
  auth/api
  expose/api
  notify/api
  uploader/api
  databox/api
)

for a in "${apps[@]}"; do
  echo "Updating $a..."
  (cd $BASEDIR/../../$a && composer update $1)
done
