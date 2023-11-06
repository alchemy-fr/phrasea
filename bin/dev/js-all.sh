#!/bin/bash

. bin/vars.sh

for a in ${JS_PROJECTS}; do
  echo " $a:$ $@"
  (cd "$a" && $@)
done
