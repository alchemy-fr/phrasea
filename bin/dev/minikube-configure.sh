#!/bin/bash

set -e

. bin/functions.sh

load-env

PHRASEA_DOMAIN="phrasea.minikube"

echo "Creating Minikube certificate for *.${PHRASEA_DOMAIN}..."

mkcert -key-file infra/certs/phrasea-minikube-key.pem -cert-file infra/certs/phrasea-minikube.pem "*.${PHRASEA_DOMAIN}"

echo "Done."
