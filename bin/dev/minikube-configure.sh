#!/bin/bash

set -e

. bin/functions.sh

load-env

# Configure host /etc/hosts
export PHRASEA_DOMAIN="phrasea.minikube"
export PHRASEA_IP=$(minikube ip)
bin/dev/append-etc-hosts.sh

# Configure minikube /etc/hosts
export IP=127.0.0.1
mkdir -p ~/.minikube/files/etc
envsubst < ./infra/dev/hosts.txt > ~/.minikube/files/etc/hosts
echo "[!] You might need to restart minikube."

echo "Creating Minikube certificate for *.${PHRASEA_DOMAIN}..."

mkcert -key-file infra/certs/phrasea-minikube-key.pem -cert-file infra/certs/phrasea-minikube.pem "*.${PHRASEA_DOMAIN}"

echo "[✓] Done"

