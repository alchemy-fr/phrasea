#!/bin/bash

. bin/vars.sh

js=(${CLIENT_PROJECTS} ${JS_LIBS})

for a in "${js[@]}"; do
  echo " $a:"

  (cd "$a" && grep -q "\"translate\":" package.json && pnpm translate) || echo "> no translate script"
done
