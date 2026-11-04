#!/bin/sh
#
# Install/upgrade lifecycle of the configurator (see doc/tech/Deployment/lifecycle.md).
#
# Usage: bin/lifecycle.sh <pre-install|post-install|pre-upgrade|post-upgrade>
#
# CONFIGURATOR_PRESETS: space-separated presets passed to the configure command
# (e.g. "test" or "dev").
#
# Every phase converges: it detects the state of the configurator database and
# can be run again safely, whatever the phase name.

set -e

BASEDIR=$(dirname "$0")
CONSOLE="${BASEDIR}/console"
WAIT_TIMEOUT="${LIFECYCLE_WAIT_TIMEOUT:-300}"

usage() {
    echo "Usage: $0 <pre-install|post-install|pre-upgrade|post-upgrade>" >&2
}

log() {
    echo "[lifecycle] configurator: $*"
}

# Usage: wait_for <description> <command...>
wait_for() {
    description="$1"
    shift
    deadline=$(($(date +%s) + WAIT_TIMEOUT))
    until "$@"; do
        if [ "$(date +%s)" -ge "${deadline}" ]; then
            echo "[lifecycle] configurator: ${description} still not ready after ${WAIT_TIMEOUT}s, giving up" >&2
            exit 1
        fi
        log "waiting for ${description}..."
        sleep 3
    done
}

# Same resolution as the configurator connection of alchemy/configurator-bundle:
# CONFIGURATOR_DATABASE_URL, else the POSTGRES_* variables with their defaults.
is_postgres_ready() {
    # PHP code, not shell expansions.
    # shellcheck disable=SC2016
    php -r '
        $url = getenv("CONFIGURATOR_DATABASE_URL");
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
    if [ ! -d "${BASEDIR}/../vendor" ]; then
        (cd "${BASEDIR}/.." && composer install)
    fi

    wait_for "PostgreSQL" is_postgres_ready

    "${CONSOLE}" doctrine:database:create --if-not-exists
}

# Usage: configure [--install]
configure() {
    for preset in ${CONFIGURATOR_PRESETS}; do
        set -- "$@" --preset "${preset}"
    done
    "${CONSOLE}" configure -vvv "$@"
}

converge() {
    # The state is the last line: a log record printed by the console may precede it.
    state=$("${CONSOLE}" alchemy:lifecycle:state --no-ansi)
    state=$(printf '%s\n' "${state}" | tail -n 1)
    log "database state: ${state}"

    case "${state}" in
        fresh)
            # configure --install builds the current state of every service, so the
            # migrations (Keycloak ones included) are only marked as executed.
            "${CONSOLE}" doctrine:schema:update -f --em=configurator
            "${CONSOLE}" doctrine:migrations:sync-metadata-storage
            configure --install
            "${CONSOLE}" doctrine:migrations:version --add --all -n
            ;;
        installed)
            "${CONSOLE}" doctrine:migrations:sync-metadata-storage
            configure
            "${CONSOLE}" doctrine:migrations:migrate -n
            ;;
        *)
            echo "[lifecycle] configurator: unexpected database state \"${state}\"" >&2
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
