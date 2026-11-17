#!/bin/sh

# On a Docker network, re-resolve the FPM host at runtime through the embedded DNS: a static
# fastcgi_pass is resolved once at startup, so a recreated FPM container (new IP) stays unreachable
# until nginx restarts. Elsewhere (Kubernetes…), the resolver would ignore the search domains of
# resolv.conf, so the address stays resolved at startup.
if grep -qE '^nameserver[[:space:]]+127\.0\.0\.11([[:space:]]|$)' /etc/resolv.conf; then
    FPM_PASS=$(printf 'resolver 127.0.0.11 valid=10s ipv6=off;\n        set $fpm_upstream %s:9000;\n        fastcgi_pass $fpm_upstream;' "${FPM_HOSTNAME}")
else
    FPM_PASS="fastcgi_pass ${FPM_HOSTNAME}:9000;"
fi
export FPM_PASS

envsubst '${FPM_PASS},${UPLOAD_MAX_FILE_SIZE},${DASHBOARD_CLIENT_URL}' < /etc/nginx/tpl/default.conf > /etc/nginx/conf.d/default.conf

exec "$@"
