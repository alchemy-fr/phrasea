#!/bin/bash

. bin/vars.sh

set -e

js=(${CLIENT_PROJECTS})

for a in "${js[@]}"; do
  echo " $a:$ $@"
  (cd "$a" && $@)
done
