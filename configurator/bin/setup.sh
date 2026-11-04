#!/bin/sh
#
# Deprecated: kept for the deployments that still call it, use bin/lifecycle.sh
# with CONFIGURATOR_PRESETS instead of the positional presets.
#
# Usage: bin/setup.sh [preset...]

set -e

BASEDIR=$(dirname "$0")

echo "bin/setup.sh is deprecated, use bin/lifecycle.sh pre-install then post-install" >&2

if [ $# -gt 0 ]; then
    export CONFIGURATOR_PRESETS="$*"
fi

"${BASEDIR}/lifecycle.sh" pre-install
"${BASEDIR}/lifecycle.sh" post-install
