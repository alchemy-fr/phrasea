#!/bin/bash

set -e

. bin/functions.sh

export IP=${PHRASEA_IP:-"127.0.0.1"}

load-env

if [ -z "${PHRASEA_DOMAIN}" ]; then
  echo "Error: Missing or empty env PHRASEA_DOMAIN";
  exit 1
fi

>&2 echo "PHRASEA_DOMAIN=${PHRASEA_DOMAIN}"
>&2 echo "IP=${IP}"

if ! grep "<${PHRASEA_DOMAIN}>" "/etc/hosts" > /dev/null ; then
  if [ "$EUID" -ne 0 ]
    then echo "Please run as root"
    exit 1
  fi
  >&2 echo "Adding domains to /etc/hosts"
  envsubst < ./infra/dev/hosts.txt | tee -a /etc/hosts > /dev/null
else
  >&2 echo "/etc/hosts already contains domains"
fi
