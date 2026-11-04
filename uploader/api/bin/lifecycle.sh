#!/bin/sh
#
# Install/upgrade lifecycle of the Uploader API (see doc/tech/Deployment/lifecycle.md).
#
# Usage: bin/lifecycle.sh <pre-install|post-install|pre-upgrade|post-upgrade>
#
# Every phase converges: it detects the state of the database and can be run
# again safely, whatever the phase name (a pre-install on an installed database
# migrates it, a pre-upgrade on an empty database installs it).

set -e

BASEDIR=$(dirname "$0")
CONSOLE="${BASEDIR}/console"
WAIT_TIMEOUT="${LIFECYCLE_WAIT_TIMEOUT:-300}"

usage() {
    echo "Usage: $0 <pre-install|post-install|pre-upgrade|post-upgrade>" >&2
}

log() {
    echo "[lifecycle] uploader: $*"
}

# Usage: wait_for <description> <command...>
wait_for() {
    description="$1"
    shift
    deadline=$(($(date +%s) + WAIT_TIMEOUT))
    until "$@"; do
        if [ "$(date +%s)" -ge "${deadline}" ]; then
            echo "[lifecycle] uploader: ${description} still not ready after ${WAIT_TIMEOUT}s, giving up" >&2
            exit 1
        fi
        log "waiting for ${description}..."
        sleep 3
    done
}

# Same resolution as config/packages/doctrine.yaml: DATABASE_URL, else the
# POSTGRES_* variables with the defaults of the application .env file.
is_postgres_ready() {
    # PHP code, not shell expansions.
    # shellcheck disable=SC2016
    php -r '
        $url = getenv("DATABASE_URL");
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
            "${CONSOLE}" doctrine:migrations:version --add --all -n
            ;;
        installed)
            "${CONSOLE}" doctrine:migrations:sync-metadata-storage
            "${CONSOLE}" doctrine:migrations:migrate -n
            ;;
        *)
            echo "[lifecycle] uploader: unexpected database state \"${state}\"" >&2
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
