#!/bin/sh
#
# Deprecated: kept for the deployments that still call it, use bin/lifecycle.sh.

set -e

BASEDIR=$(dirname "$0")

echo "bin/migrate.sh is deprecated, use bin/lifecycle.sh pre-upgrade then post-upgrade" >&2

"${BASEDIR}/lifecycle.sh" pre-upgrade
"${BASEDIR}/lifecycle.sh" post-upgrade
