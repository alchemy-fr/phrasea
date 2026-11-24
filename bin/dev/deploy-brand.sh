#!/bin/bash
#
# Deploys the logos of brand/*.svg into the apps (see brand/README.md).
#
# Usage: bin/dev/deploy-brand.sh [logo...]   (run from the repository root)

set -e

. bin/functions.sh

load-env

# The entrypoint of the dev container exits without running anything when no
# TTY is attached: bypass it so that the command also runs from scripts.
docker compose run --rm -T --entrypoint sh dev -c 'su app -c "pnpm brand $*"' -- "$@"
