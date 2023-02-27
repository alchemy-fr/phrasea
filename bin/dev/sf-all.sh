#!/bin/bash

. bin/vars.sh

for a in ${SYMFONY_PROJECTS}; do
  echo " $a:$ $@"
  (cd "$a" && $@)
done
