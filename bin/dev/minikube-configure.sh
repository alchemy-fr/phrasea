#!/bin/bash

set -e

. bin/functions.sh

load-env

# Configure host /etc/hosts
export PHRASEA_DOMAIN="phrasea.minikube"
sudo bin/dev/append-etc-hosts.sh

# Configure minikube /etc/hosts
export IP=127.0.0.1
mkdir -p ~/.minikube/files/etc
envsubst < ./infra/dev/hosts.txt > ~/.minikube/files/etc/hosts
echo "[!] You might need to restart minikube."

echo "Creating Minikube certificate for *.${PHRASEA_DOMAIN}..."

mkcert -key-file infra/certs/phrasea-minikube-key.pem -cert-file infra/certs/phrasea-minikube.pem "*.${PHRASEA_DOMAIN}"

sudo rm -rf /etc/nginx/ssl/phrasea.minikube
sudo mkdir -p /etc/nginx/ssl/phrasea.minikube
sudo cp infra/certs/phrasea-minikube.pem /etc/nginx/ssl/phrasea.minikube/phrasea-minikube.pem
sudo cp infra/certs/phrasea-minikube-key.pem /etc/nginx/ssl/phrasea.minikube/phrasea-minikube-key.pem

echo "[✓] Done"

