#!/bin/sh

# The origin of the client (scheme://host[:port], lowercased, without the default port of the scheme), as
# sent by the browsers and computed by the API (`%env(origin:EXPOSE_CLIENT_URL)%`) for CORS and the purges.
client_origin() {
    printf '%s' "$1" \
        | tr '[:upper:]' '[:lower:]' \
        | sed -nE 's|^[[:space:]]*(https?)://([^/?#@[:space:]]*@)?([^/?#@:[:space:]]+)(:[0-9]+)?([/?#][^[:space:]]*)?[[:space:]]*$|\1://\3\4|p' \
        | sed -E 's|^(http://[^:]+):80$|\1|; s|^(https://[^:]+):443$|\1|'
}

PS_CLIENT_ORIGIN_MAP_ENTRY=''
EXPOSE_CLIENT_ORIGIN=$(client_origin "${EXPOSE_CLIENT_URL:-}")
if [ -n "${EXPOSE_CLIENT_ORIGIN}" ]; then
    PS_CLIENT_ORIGIN_MAP_ENTRY="    \"${EXPOSE_CLIENT_ORIGIN}\" 0;"
else
    echo "EXPOSE_CLIENT_URL is not a valid URL: the requests of the Expose client bypass the proxy cache." >&2
fi
export PS_CLIENT_ORIGIN_MAP_ENTRY

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

envsubst '${FPM_PASS},${UPLOAD_MAX_FILE_SIZE},${DASHBOARD_CLIENT_URL},${PS_CLIENT_ORIGIN_MAP_ENTRY}' < /etc/nginx/tpl/default.conf > /etc/nginx/conf.d/default.conf

exec "$@"
