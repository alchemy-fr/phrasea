#!/bin/bash

. bin/vars.sh

apps=(${SYMFONY_PROJECTS} ${PHP_LIBS})

for a in "${apps[@]}"; do
  echo " $a:$ $@"
  (cd "$a" && $@)
done
