#!/bin/bash

. bin/vars.sh

for a in ${CLIENT_PROJECTS}; do
  echo " $a:$ $@"
  (cd "$a" && $@)
done
