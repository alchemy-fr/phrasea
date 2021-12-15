#!/bin/bash

apps=(
  auth/api
  expose/api
  notify/api
  uploader/api
  databox/api
)

for a in "${apps[@]}"; do
  echo " $a:$ $@"
  (cd "$a" && $@)
done
