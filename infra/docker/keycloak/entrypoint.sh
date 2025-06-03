#!/bin/sh

set -e

envsubst '${KC_LOGIN_BACKGROUND_IMAGE_URL}' < /opt/keycloak/themes/phrasea/login/resources/css/phrasea/login-phrasea.css > /opt/keycloak/themes/phrasea/login/resources/css/phrasea/login.css

exec  /opt/keycloak/bin/kc.sh "$@"
