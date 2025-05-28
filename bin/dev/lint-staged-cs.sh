#!/bin/bash

if [ ! -d "./vendor" ]; then
  echo "No vendor directory found. Skipping lint-staged checks."
  composer install
fi

composer cs
