#!/bin/bash

. bin/vars.sh

apps=(${SYMFONY_PROJECTS})

if [ "${INCLUDE_LIBS}" == "1" ]; then
  apps=(${SYMFONY_PROJECTS} ${PHP_LIBS})
fi

for a in "${apps[@]}"; do
  echo "Updating $a..."
  (cd "$a" && composer install)
done
