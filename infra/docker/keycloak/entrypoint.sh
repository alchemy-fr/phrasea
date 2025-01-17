#!/bin/sh

set -e

sed -i "s;dashboard_url;$DASHBOARD_CLIENT_URL;g" /opt/keycloak/themes/phrasea/email/html/password-reset.ftl


sed -i "s;dashboard_url;$DASHBOARD_CLIENT_URL;g" /opt/keycloak/themes/phrasea/email/text/password-reset.ftl

/opt/keycloak/bin/kc.sh start --optimized
