#!/bin/bash

. bin/vars.sh

apps=(${JS_PROJECTS})

for a in "${apps[@]}"; do
  echo "Installing $a..."
  (cd "$a" && yarn install)
done
