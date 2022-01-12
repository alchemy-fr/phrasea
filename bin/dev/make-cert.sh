#!/bin/bash

set -e

. bin/functions.sh

load-env

if [ -z "${PHRASEA_DOMAIN}" ]; then
  >&2 echo "Missing PHRASEA_DOMAIN env"
  exit 1
fi

echo "Creating local certificate for *.${PHRASEA_DOMAIN}..."

mkcert -key-file infra/certs/phrasea-key.pem -cert-file infra/certs/phrasea.pem "*.${PHRASEA_DOMAIN}"

echo "Done."
