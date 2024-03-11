#!/bin/bash

. bin/vars.sh

js=(${CLIENT_PROJECTS} ${NODE_PROJECTS})

for a in "${js[@]}"; do
  echo " $a:$ $@"
  (cd "$a" && $@)
done
