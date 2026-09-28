#!/bin/bash

. bin/vars.sh

set -e

for a in ${PHP_LIBS}; do
  echo " $a:$ $@"
  (cd "$a" && $@)
done
