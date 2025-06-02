#!/bin/sh

set -e

sed -i 's|background-image|image|g' /opt/keycloak/themes/phrasea/login/resources/css/phrasea/login.css

exec  /opt/keycloak/bin/kc.sh "$@"
