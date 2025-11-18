#!/bin/bash

. bin/vars.sh

set -e

apps=(${SYMFONY_PROJECTS} ${PHP_LIBS})

for a in "${apps[@]}"; do
  echo " $a:$ $@"
  (cd "$a" && $@)
done
