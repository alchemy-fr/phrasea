#!/bin/sh

# exec, and node rather than `pnpm console`: the process that has to receive
# SIGTERM is the one that handles it (src/shutdown.ts). Behind a shell or a
# pnpm wrapper it is the wrapper that becomes PID 1, and the kernel drops a
# SIGTERM that PID 1 has no handler for — `docker stop` then waits out its
# whole grace period and SIGKILLs the indexation mid-flight.
#
# The bundle is resolved from this script rather than from the working
# directory, which configLoader reads the config relative to.
exec node "$(dirname "$0")/../dist/console.mjs" "$@"
