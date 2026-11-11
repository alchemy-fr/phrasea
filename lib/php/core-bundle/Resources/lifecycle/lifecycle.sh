#!/bin/sh
#
# Install/upgrade lifecycle shared by the applications (see doc/tech/Deployment/lifecycle.md),
# called by the bin/lifecycle.sh of each application.
#
# Usage: lifecycle.sh <pre-install|post-install|pre-upgrade|post-upgrade>
#
# Set by the calling bin/lifecycle.sh:
# - LIFECYCLE_APP: name of the application, in the logs;
# - LIFECYCLE_APP_DIR: root directory of the application;
# - LIFECYCLE_DATABASE_URL_VAR: variable holding the database URL (default DATABASE_URL),
#   resolved like the Doctrine connection: that URL, else the POSTGRES_* variables.
#
# Every phase converges: it detects the state of the database and can be run
# again safely, whatever the phase name (a pre-install on an installed database
# migrates it, a pre-upgrade on an empty database installs it).
#
# The application adds its own steps as hooks, bin/lifecycle.d/<NN>-<name>.<step>.sh,
# sourced in name order (so with CONSOLE, log and wait_for at hand) at these steps:
# - prepare: after the database creation, before the state detection;
# - install: on a fresh database, between the schema creation and the migrations
#   being marked as executed;
# - upgrade: on an installed database, before the migrations run.

set -e

: "${LIFECYCLE_APP:?}"
: "${LIFECYCLE_APP_DIR:?}"

CONSOLE="${LIFECYCLE_APP_DIR}/bin/console"
HOOKS_DIR="${LIFECYCLE_APP_DIR}/bin/lifecycle.d"
WAIT_TIMEOUT="${LIFECYCLE_WAIT_TIMEOUT:-300}"

usage() {
    echo "Usage: bin/lifecycle.sh <pre-install|post-install|pre-upgrade|post-upgrade>" >&2
}

log() {
    echo "[lifecycle] ${LIFECYCLE_APP}: $*"
}

# Usage: wait_for <description> <command...>
wait_for() {
    description="$1"
    shift
    deadline=$(($(date +%s) + WAIT_TIMEOUT))
    until "$@"; do
        if [ "$(date +%s)" -ge "${deadline}" ]; then
            echo "[lifecycle] ${LIFECYCLE_APP}: ${description} still not ready after ${WAIT_TIMEOUT}s, giving up" >&2
            exit 1
        fi
        log "waiting for ${description}..."
        sleep 3
    done
}

# Usage: run_hooks <step>
run_hooks() {
    for lifecycle_hook in "${HOOKS_DIR}"/*."$1".sh; do
        [ -f "${lifecycle_hook}" ] || continue
        log "hook $(basename "${lifecycle_hook}")"
        # shellcheck disable=SC1090
        . "${lifecycle_hook}"
    done
}

is_postgres_ready() {
    # PHP code, not shell expansions.
    # shellcheck disable=SC2016
    php -r '
        $url = getenv(getenv("LIFECYCLE_DATABASE_URL_VAR") ?: "DATABASE_URL");
        if ($url) {
            $parts = parse_url($url);
            $host = $parts["host"] ?? "db";
            $port = $parts["port"] ?? 5432;
        } else {
            $host = getenv("POSTGRES_HOST") ?: "db";
            $port = getenv("POSTGRES_PORT") ?: 5432;
        }
        exit(@fsockopen($host, (int) $port, $errno, $errstr, 2) ? 0 : 1);
    '
}

prepare() {
    wait_for "PostgreSQL" is_postgres_ready

    "${CONSOLE}" doctrine:database:create --if-not-exists

    run_hooks prepare
}

converge() {
    # The state is the last line: a log record printed by the console may precede it.
    state=$("${CONSOLE}" alchemy:lifecycle:state --no-ansi)
    state=$(printf '%s\n' "${state}" | tail -n 1)
    log "database state: ${state}"

    case "${state}" in
        fresh)
            # The only path that resets anything: no migration is recorded yet.
            "${CONSOLE}" doctrine:schema:update -f
            "${CONSOLE}" doctrine:migrations:sync-metadata-storage
            run_hooks install
            "${CONSOLE}" doctrine:migrations:version --add --all -n
            ;;
        installed)
            "${CONSOLE}" doctrine:migrations:sync-metadata-storage
            run_hooks upgrade
            "${CONSOLE}" doctrine:migrations:migrate -n
            ;;
        *)
            echo "[lifecycle] ${LIFECYCLE_APP}: unexpected database state \"${state}\"" >&2
            exit 1
            ;;
    esac
}

case "$1" in
    pre-install | pre-upgrade)
        log "$1"
        prepare
        converge
        ;;
    post-install | post-upgrade)
        # Extension point of the lifecycle contract: nothing to do yet.
        ;;
    *)
        usage
        exit 64
        ;;
esac
