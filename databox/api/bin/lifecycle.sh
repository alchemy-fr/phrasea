#!/bin/sh
#
# Install/upgrade lifecycle of the Databox API (see doc/tech/Deployment/lifecycle.md):
# the common steps of alchemy/core-bundle, plus the hooks of bin/lifecycle.d.
#
# Usage: bin/lifecycle.sh <pre-install|post-install|pre-upgrade|post-upgrade>

set -e

APP_DIR=$(cd "$(dirname "$0")/.." && pwd)

# The common steps ship with the vendors, installed by the image build: only a
# development checkout may lack them, composer never runs anywhere else.
if [ ! -d "${APP_DIR}/vendor" ]; then
    if [ "${APP_ENV}" != "dev" ]; then
        echo "[lifecycle] databox: ${APP_DIR}/vendor is missing (APP_ENV=${APP_ENV:-unset})" >&2
        exit 1
    fi
    (cd "${APP_DIR}" && composer install)
fi

export LIFECYCLE_APP=databox
export LIFECYCLE_APP_DIR="${APP_DIR}"

exec sh "${APP_DIR}/vendor/alchemy/core-bundle/Resources/lifecycle/lifecycle.sh" "$@"
