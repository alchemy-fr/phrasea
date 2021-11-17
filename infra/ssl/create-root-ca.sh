#!/bin/bash
set -e

mkdir -p ~/ssl/
read -s -p "Enter passphrase: " pass

openssl genrsa -des3 -passout pass:${pass} -out ~/ssl/AlchemyDevelopmentRootCA.key 2048
openssl req -x509 -new -nodes -key ~/ssl/AlchemyDevelopmentRootCA.key -sha256 -days 397 \
    -subj "/C=FR/ST=France/L=Paris/O=Alchemy, Inc./OU=Development, use ONLY for development/CN=Alchemy Development Root CA" \
    -passin pass:${pass} \
    -out ~/ssl/AlchemyDevelopmentRootCA.pem

echo "Done."

