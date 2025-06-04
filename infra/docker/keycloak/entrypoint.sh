#!/bin/sh

set -e

sed -e "s|##KC_LOGIN_CSS_BACKGROUND##|$KC_LOGIN_CSS_BACKGROUND|g" -e "s|##KC_LOGIN_CSS_LOGO_BACKGROUND##|$KC_LOGIN_CSS_LOGO_BACKGROUND|g" < /opt/keycloak/themes/phrasea/login/resources/css/phrasea/login-phrasea.css > /opt/keycloak/themes/phrasea/login/resources/css/phrasea/login.css

exec  /opt/keycloak/bin/kc.sh "$@"
