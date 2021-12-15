#!/bin/bash

if [ -z "${PHRASEA_DOMAIN}" ]; then
  echo "Error: Missing or empty env PHRASEA_DOMAIN";
  exit 1
fi

if ! grep 'phrasea-local' "/etc/hosts" > /dev/null ; then
  if [ "$EUID" -ne 0 ]
    then echo "Please run as root"
    exit 1
  fi
  echo "Adding domains to /etc/hosts"
  envsubst < ./infra/dev/hosts.txt | tee -a /etc/hosts > /dev/null
else
  echo "/etc/hosts already contains domains"
fi
