#!/bin/bash
#
# End-to-end test for databox/indexer against the running Docker stack.
#
#   1. provision a deterministic fixture tree and the indexer config into a
#      shared volume            (databox/indexer/tests/e2e/01-provision.test.ts)
#   2. index it through the real databox API
#   3. check the result in databox  (tests/e2e/02-indexation.test.ts)
#   4. index it again
#   5. check that nothing was duplicated  (tests/e2e/03-idempotence.test.ts)
#   6. remove the workspace         (tests/e2e/99-cleanup.test.ts)
#
# Every step runs in the databox-indexer container: the steps that assert are
# vitest files driven one at a time, the indexation is the console command.
# This script only orchestrates.
#
# Usage: bin/dev/test-indexer-e2e.sh [options]   (run from the repository root)

set -euo pipefail

usage() {
    cat <<'USAGE'
Usage: bin/dev/test-indexer-e2e.sh [options]

  --slug <slug>        Workspace slug to index into (default: indexer-e2e)
  --concurrency <n>    DATABOX_CONCURRENCY for the indexer (default: 1)
  --timeout <seconds>  Timeout of a single indexation (default: 300)
  --skip-build         Do not rebuild databox/indexer/dist first
  --skip-idempotence   Index once instead of twice
  --keep               Keep the workspace and the /e2e volume
  -h, --help           Show this help
USAGE
}

E2E_CONCURRENCY="1"
E2E_TIMEOUT="300"
SKIP_BUILD="0"
SKIP_IDEMPOTENCE="0"
KEEP="0"

while [ $# -gt 0 ]; do
    case "$1" in
        --slug) export INDEXER_E2E_SLUG="$2"; shift 2 ;;
        --concurrency) E2E_CONCURRENCY="$2"; shift 2 ;;
        --timeout) E2E_TIMEOUT="$2"; shift 2 ;;
        --skip-build) SKIP_BUILD="1"; shift ;;
        --skip-idempotence) SKIP_IDEMPOTENCE="1"; shift ;;
        --keep) KEEP="1"; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage >&2; exit 2 ;;
    esac
done

if [ ! -f "bin/functions.sh" ] || [ ! -d "databox/indexer" ]; then
    echo "ERROR: run this script from the repository root." >&2
    exit 2
fi

# shellcheck source=/dev/null
. bin/functions.sh
# load-env re-exports the current environment through `eval`, so a value that
# already contains a `$` — ADMIN_BASIC_AUTH_USER holds an $apr1$ hash — looks
# like an unset variable to it. Every other script in bin/ runs without `set
# -u` and ignores this; do the same for that one call only.
set +u
load-env
set -u

export INDEXER_E2E_SLUG="${INDEXER_E2E_SLUG:-indexer-e2e}"

# Merged in rather than passed as --profile, which *replaces* COMPOSE_PROFILES
# for the whole invocation.
export COMPOSE_PROFILES="${COMPOSE_PROFILES:-},indexer"

if [ -t 1 ]; then
    C_OK=$'\033[32m'; C_KO=$'\033[31m'; C_DIM=$'\033[2m'; C_OFF=$'\033[0m'
else
    C_OK=""; C_KO=""; C_DIM=""; C_OFF=""
fi

info() { echo "${C_DIM}--- $*${C_OFF}"; }
die() { echo "${C_KO}ERROR:${C_OFF} $*" >&2; exit 1; }

APP_DIR="/srv/workspace/phrasea/databox/indexer"

############################################################
# Checked here rather than in the suite: the containers cannot see the Docker
# daemon, so a stopped stack reaches them as a DNS failure on `databox-api`.
# redis is in the list because it backs the Symfony cache: without it every
# request spends 30s failing to reach it, which trips the indexer's own 30s
# axios timeout and fails the indexation halfway through.
REQUIRED_SERVICES=(db elasticsearch keycloak redis databox-api-php databox-api-nginx)
# In APP_ENV=dev every Messenger transport is sync://, so the websocket
# broadcast that follows POST /assets runs inside the request: a missing soketi
# turns into a 500, and so does a missing traefik, since the Pusher client
# reaches soketi through its public URL.
if [ "${APP_ENV:-prod}" = "dev" ]; then
    REQUIRED_SERVICES+=(soketi traefik)
fi

RUNNING="$(docker compose ps --services --status running 2>/dev/null || true)"
MISSING=()
for svc in "${REQUIRED_SERVICES[@]}"; do
    grep -qx "${svc}" <<<"${RUNNING}" || MISSING+=("${svc}")
done
if [ "${#MISSING[@]}" -gt 0 ]; then
    die "services not running: ${MISSING[*]}
  Start them with:  docker compose up -d ${MISSING[*]}
  See doc/tech/01_setup.md for the full bootstrap."
fi

# Named volume rather than a bind mount: it is written by the step that runs as
# root and read by the indexer running as `node`, and neither path has to exist
# on the host.
E2E_VOLUME="indexer-e2e-$$"

# Set while an indexation container is running. `docker compose run` does not
# stop the container when the client attached to it is killed, so a run the
# script gave up on would otherwise keep writing to databox behind its back.
INDEXER_CONTAINER=""

# The flags every step of the suite shares. DATABOX_API_URL is overridden: the
# computed value goes through Traefik on the host, which does not necessarily
# listen on the port it implies.
indexer_flags() {
    printf '%s\n' \
        --rm -T \
        -v "${E2E_VOLUME}:/e2e" \
        -e DATABOX_API_URL=http://databox-api \
        -e DATABOX_VERIFY_SSL=false \
        -e DATABOX_CONCURRENCY="${E2E_CONCURRENCY}" \
        -e INDEXER_E2E_SLUG="${INDEXER_E2E_SLUG}"
}

# One vitest file, from the package directory.
e2e_step() {
    local flags=()
    mapfile -t flags < <(indexer_flags)

    docker compose run "${flags[@]}" \
        --workdir "${APP_DIR}" --entrypoint pnpm databox-indexer \
        test:e2e "tests/e2e/$1" </dev/null
}

cleanup() {
    local rc=$?
    trap - EXIT INT TERM

    if [ -n "${INDEXER_CONTAINER}" ]; then
        docker rm -f "${INDEXER_CONTAINER}" >/dev/null 2>&1 || true
    fi

    if [ "${KEEP}" = "1" ]; then
        echo "Volume ${E2E_VOLUME} kept (docker volume rm ${E2E_VOLUME})."
    else
        info "Removing the workspace and the fixtures"
        e2e_step 99-cleanup.test.ts >/dev/null 2>&1 \
            || echo "WARNING: could not remove the workspace \"${INDEXER_E2E_SLUG}\"." >&2
        docker volume rm "${E2E_VOLUME}" >/dev/null 2>&1 || true
    fi

    exit "${rc}"
}

############################################################
# The indexation runs databox/indexer/dist/console.mjs. Only the dev override
# bind-mounts the repo over the databox-indexer image, which is what makes a
# build land on the host; without it the image ships its own.
case "${COMPOSE_FILE:-}" in
    *docker-compose.dev.yml*)
        if [ "${SKIP_BUILD}" != "1" ]; then
            info "Building databox/indexer"
            docker compose run --rm -T databox-indexer pnpm build \
                || die "pnpm build failed."
        fi
        [ -f databox/indexer/dist/console.mjs ] \
            || die "databox/indexer/dist/console.mjs is missing.
  Build it with:  docker compose run --rm databox-indexer pnpm build"
        ;;
esac

docker volume create "${E2E_VOLUME}" >/dev/null
trap cleanup EXIT INT TERM

# The mount point of a fresh volume belongs to root, while every step below
# runs as the unprivileged user of the image.
docker compose run --rm -T --user root -v "${E2E_VOLUME}:/e2e" \
    --entrypoint /bin/sh databox-indexer -c 'chmod 0777 /e2e' >/dev/null \
    || die "could not prepare the ${E2E_VOLUME} volume."

############################################################

run_indexer() {
    local run_no="$1"
    local rc=0

    # --workdir /e2e is what makes configLoader read
    # /e2e/config/config.e2e.json; everything else the location needs is
    # already a literal in that file.
    #
    # The output is streamed through `tee` rather than redirected and dumped at
    # the end: a run that stalls has to show where. The exit code goes through
    # a file because busybox sh has no PIPESTATUS.
    local flags=()
    mapfile -t flags < <(indexer_flags)

    INDEXER_CONTAINER="phrasea-indexer-e2e-$$-${run_no}"

    # The deadline is enforced *inside* the container, by a `timeout` whose
    # child is node itself. Killing the `docker compose run` client from out
    # here stops nothing: the container's PID 1 is a shell, and the kernel
    # drops a SIGTERM that PID 1 has no handler for. The outer timeout is only
    # a backstop for a docker client that is itself stuck, and the container is
    # removed by name whatever happens.
    #
    # --foreground, and stdin from /dev/null: without the first, `timeout` puts
    # the command in a process group of its own, which is not the foreground
    # group of the terminal; `docker compose run` then touches the tty and the
    # kernel stops it with SIGTTIN. The run hangs, and being stopped it does
    # not even answer SIGTERM. It only ever happens on a terminal, which is why
    # a redirected run never shows it.
    set +e
    timeout --foreground --kill-after=10 "$((E2E_TIMEOUT + 30))" \
        docker compose run "${flags[@]}" \
        --name "${INDEXER_CONTAINER}" \
        --workdir /e2e \
        -e CONFIG_FILE=config.e2e.json \
        --entrypoint /bin/sh \
        databox-indexer \
        -c "{ timeout ${E2E_TIMEOUT} node ${APP_DIR}/dist/console.mjs \
                  index e2e_fs --no-server --debug 2>&1
              echo \$? >/e2e/run${run_no}.rc
            } | tee /e2e/run${run_no}.log
            exit \$(cat /e2e/run${run_no}.rc)" </dev/null
    rc=$?
    set -e

    docker rm -f "${INDEXER_CONTAINER}" >/dev/null 2>&1 || true
    INDEXER_CONTAINER=""

    # The indexer turns SIGTERM into 143 itself; busybox timeout reports 143
    # too, or 137 once it had to escalate to SIGKILL. The outer GNU timeout
    # reports 124.
    case "${rc}" in
        124 | 137 | 143)
            die "the indexation did not finish within ${E2E_TIMEOUT}s."
            ;;
        0) ;;
        *) die "the indexer exited with code ${rc}." ;;
    esac
}

############################################################
info "Provisioning the fixtures"
e2e_step 01-provision.test.ts || die "the fixture provisioning failed."

info "Indexing (run 1)"
run_indexer 1

info "Checking what landed in databox"
e2e_step 02-indexation.test.ts || die "the verification of the first run failed."

if [ "${SKIP_IDEMPOTENCE}" != "1" ]; then
    info "Indexing again (idempotence)"
    run_indexer 2

    info "Checking that nothing was duplicated"
    e2e_step 03-idempotence.test.ts || die "the idempotence checks failed."
fi

echo
echo "${C_OK}Indexer end-to-end suite passed.${C_OFF}"
