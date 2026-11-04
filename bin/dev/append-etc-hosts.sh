#!/bin/bash

set -e

. bin/functions.sh

export IP=${PHRASEA_IP:-"127.0.0.1"}

load-env

if [ -z "${PHRASEA_DOMAIN}" ]; then
  echo "Error: Missing or empty env PHRASEA_DOMAIN";
  exit 1
fi

>&2 echo "PHRASEA_DOMAIN=${PHRASEA_DOMAIN}"
>&2 echo "IP=${IP}"

START="# <${PHRASEA_DOMAIN}>"
END="# </${PHRASEA_DOMAIN}>"
EXPECTED=$(envsubst < ./infra/dev/hosts.txt)
CURRENT=$(sed -n "\|^${START}\$|,\|^${END}\$|p" /etc/hosts)

if [ "${CURRENT}" = "${EXPECTED}" ]; then
  >&2 echo "/etc/hosts already contains domains"
  exit 0
fi

if [ -n "${CURRENT}" ] && ! grep -qxF "${END}" /etc/hosts; then
  echo "Error: /etc/hosts has \"${START}\" without \"${END}\", fix it by hand"
  exit 1
fi

function entries {
  awk '!/^[[:space:]]*#/ && NF { $1 = $1; print }' | LC_ALL=C sort -u
}

MISSING=$(LC_ALL=C comm -23 <(entries <<< "${EXPECTED}") <(entries <<< "${CURRENT}"))
OBSOLETE=$(LC_ALL=C comm -13 <(entries <<< "${EXPECTED}") <(entries <<< "${CURRENT}"))

if [ -n "${CURRENT}" ]; then
  [ -n "${MISSING}" ] && >&2 echo "Domains to add:" && >&2 sed 's/^/  + /' <<< "${MISSING}"
  [ -n "${OBSOLETE}" ] && >&2 echo "Domains to remove:" && >&2 sed 's/^/  - /' <<< "${OBSOLETE}"
fi

if [ "$EUID" -ne 0 ]; then
  if [ -n "${CURRENT}" ]; then
    echo "Warning: /etc/hosts domains are outdated, run: sudo $0"
    exit 0
  fi
  echo "Please run as root"
  exit 1
fi

if [ -n "${CURRENT}" ]; then
  >&2 echo "Updating domains in /etc/hosts"
  HOSTS=$(START="${START}" END="${END}" BLOCK="${EXPECTED}" awk '
    $0 == ENVIRON["START"] { if (!done) print ENVIRON["BLOCK"]; done = 1; skip = 1; next }
    skip { if ($0 == ENVIRON["END"]) skip = 0; next }
    { print }
  ' /etc/hosts)
  printf '%s\n' "${HOSTS}" > /etc/hosts
else
  >&2 echo "Adding domains to /etc/hosts"
  printf '%s\n' "${EXPECTED}" >> /etc/hosts
fi
