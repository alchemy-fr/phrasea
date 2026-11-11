#!/bin/sh
#
# Deprecated: kept for the deployments that still call it, use bin/lifecycle.sh.

set -e

BASEDIR=$(dirname "$0")

echo "bin/setup.sh is deprecated, use bin/lifecycle.sh pre-install then post-install" >&2

"${BASEDIR}/lifecycle.sh" pre-install
"${BASEDIR}/lifecycle.sh" post-install
