#!/bin/bash

. bin/vars.sh

set -e

js=(${CLIENT_PROJECTS} ${JS_LIBS})

for a in "${js[@]}"; do
  echo " $a:"
  if grep -q "\"translate\":" "$a/package.json"; then
    (cd "$a" && pnpm translate)
  else
    echo "  No translations configured."
  fi
done
