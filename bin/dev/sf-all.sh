#!/bin/bash

for a in ${SYMFONY_PROJECTS}; do
  echo " $a:$ $@"
  (cd "$a" && $@)
done
