#!/bin/bash

. bin/vars.sh

for a in ${PHP_LIBS}; do
  echo " $a:$ $@"
  (cd "$a" && $@)
done
