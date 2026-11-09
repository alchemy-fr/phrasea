#!/bin/sh

function export_env_from_file {
  if [ ! -f "$1" ]; then
      return
  fi

  set -o allexport
  source $1
  set +o allexport
}

# Export env vars from defaults
# Defined env vars take precedence, then .env.local, then .env
# Usage: load-env
function load-env {
  if [ ! -f ".env" ]; then
    >&2 echo ".env file not found at $(pwd)"
    exit 1
  fi

  tmp="/tmp/env-$(cat /dev/urandom | LC_ALL=C tr -dc 'A-Za-z0-9' | head -c 13 ; echo '')"
  env > "${tmp}"

  export_env_from_file ".env.local"
  export_env_from_file ".env"
  export_env_from_file ".env.local"

  eval "$(
    while read -r LINE; do
      if [[ $LINE =~ ^[A-Za-z0-9_]+= ]] && [[ $LINE != '#'* ]]; then
        key=$(printf '%s\n' "$LINE"| sed 's/"/\\"/g' | cut -d '=' -f 1)
        value=$(printf '%s\n' "$LINE" | cut -d '=' -f 2- | sed 's/"/\\\"/g')
        printf '%s\n' "export $key=\"$value\""
      fi
    done < "${tmp}"
  )"

  rm -f "${tmp}"
}

# Whether the stack runs as a development one: APP_ENV=dev or the dev compose
# file in COMPOSE_FILE (see doc/tech/02_dev.md).
function is_dev_env {
  [ "${APP_ENV}" = "dev" ] && return 0
  case ":${COMPOSE_FILE}:" in
    *:docker-compose.dev.yml:*) return 0 ;;
  esac
  return 1
}

# The ADMIN_BASIC_AUTH_USER value versioned in the repository's .env. Read from
# git first, so that a .env edited in place is still compared to the public one.
function versioned_admin_basic_auth_user {
  local content line
  content=$(git show HEAD:.env 2>/dev/null) || content=$(cat .env)
  line=$(grep -m1 '^ADMIN_BASIC_AUTH_USER=' <<< "${content}") || return 0
  (
    unset ADMIN_BASIC_AUTH_USER
    eval "${line}"
    printf '%s' "${ADMIN_BASIC_AUTH_USER}"
  )
}

# Refuse to deploy a non-development stack with the public default secrets of
# .env: values still starting with __CHANGE_ME_, and the versioned
# ADMIN_BASIC_AUTH_USER, whose password is published in .env.
# PHRASEA_ALLOW_DEFAULT_SECRETS=1 skips the check (demonstrations, CI).
# Usage (after load-env): check_default_secrets
function check_default_secrets {
  if is_dev_env; then
    return 0
  fi
  if [ "${PHRASEA_ALLOW_DEFAULT_SECRETS}" = "1" ]; then
    >&2 echo "Warning: PHRASEA_ALLOW_DEFAULT_SECRETS=1, the default secrets are not checked."
    return 0
  fi

  local name default_admin
  local defaults=()
  for name in $(compgen -e | sort); do
    if [[ "${!name}" == __CHANGE_ME_* ]]; then
      defaults+=("${name}")
    fi
  done

  default_admin=$(versioned_admin_basic_auth_user)
  if [ -n "${default_admin}" ] && [ "${ADMIN_BASIC_AUTH_USER}" = "${default_admin}" ]; then
    defaults+=("ADMIN_BASIC_AUTH_USER")
  fi

  if [ ${#defaults[@]} -eq 0 ]; then
    return 0
  fi

  >&2 echo "Refusing to deploy with the default secrets of .env (APP_ENV=${APP_ENV:-<empty>}, not a dev stack)."
  >&2 echo "Override these variables in .env.local:"
  for name in "${defaults[@]}"; do
    >&2 echo "  - ${name}"
  done
  >&2 echo "Generate ADMIN_BASIC_AUTH_USER with: htpasswd -nb admin 'my-secret-password'"
  >&2 echo "Set PHRASEA_ALLOW_DEFAULT_SECRETS=1 to deploy anyway (demonstration only)."
  exit 1
}

# The services to pass to `docker compose up -d`: none (= every service of the
# active profiles) unless Keycloak configuration is disabled. Keycloak has no
# profile, so the other services are then listed explicitly, which keeps
# Compose from pulling or starting it.
# Usage: docker compose up -d $(compose_up_services)
function compose_up_services {
  if [ "${CONFIGURATOR_CONFIGURE_KEYCLOAK}" = "false" ]; then
    docker compose config --services | grep -vx keycloak
  fi
}

# Create or update the user of the applications on the bundled Elasticsearch,
# and wait for it: `docker compose up` also runs `elasticsearch-setup`, but in
# the background. Nothing to do without the `elasticsearch` profile (external
# cluster).
# Usage: setup_elasticsearch_users
function setup_elasticsearch_users {
  if docker compose config --services | grep -qx elasticsearch-setup; then
    docker compose run --rm -T elasticsearch-setup
  fi
}

# Create or update the MinIO user of each consumer whose *_S3_ACCESS_KEY pair is
# set (minio-setup, profile `setup`). Nothing to do without the `minio` profile
# (external S3, whose keys are managed there).
# Usage: setup_minio_users
function setup_minio_users {
  if docker compose config --services | grep -qx minio; then
    docker compose run --rm -T minio-setup
  fi
}

# The infrastructure services the lifecycle phases need (see
# doc/tech/Deployment/lifecycle.md), started before pre-install and pre-upgrade.
# Keycloak is left out when its configuration is disabled, and so is every service
# whose profile is not active (an external database, search engine or bucket):
# naming a service on the command line would start it whatever COMPOSE_PROFILES says.
# Usage: docker compose up -d $(infra_services)
function infra_services {
  local wanted="traefik minio rabbitmq db redis elasticsearch"
  if [ "${CONFIGURATOR_CONFIGURE_KEYCLOAK}" != "false" ]; then
    wanted="${wanted} keycloak"
  fi

  local enabled
  enabled=$(docker compose config --services)

  local service
  local services=""
  for service in ${wanted}; do
    if printf '%s\n' "${enabled}" | grep -qx "${service}"; then
      services="${services} ${service}"
    fi
  done
  echo "${services}"
}

LIFECYCLE_PIDS=()
LIFECYCLE_CONTAINERS=()

# Force-remove the containers started by run_phase: killing the
# "docker compose run" client is not enough, the container keeps running.
function lifecycle_remove_containers {
  local name
  for name in "${LIFECYCLE_CONTAINERS[@]}"; do
    docker rm -f "${name}" >/dev/null 2>&1 || true
  done
  LIFECYCLE_CONTAINERS=()
}

function lifecycle_interrupt {
  # A second Ctrl+C gives up on the cleanup instead of being ignored.
  trap 'echo "Forced exit."; exit 130' INT TERM

  echo
  echo "Interrupted, stopping lifecycle tasks..."

  local pid
  for pid in "${LIFECYCLE_PIDS[@]}"; do
    kill -TERM "${pid}" 2>/dev/null || true
  done

  lifecycle_remove_containers

  wait 2>/dev/null || true

  exit 130
}

# Run a lifecycle phase (bin/lifecycle.sh <phase>) in one-off containers of the
# given services, so with the image the stack is about to run, in parallel.
# Returns non-zero if any of them failed.
# Usage: run_phase <phase> <service...>
function run_phase {
  local phase="$1"
  shift

  local env_args=()
  local var
  for var in CONFIGURATOR_PRESETS LIFECYCLE_WAIT_TIMEOUT; do
    if [ -n "${!var+x}" ]; then
      env_args+=(-e "${var}")
    fi
  done

  trap lifecycle_interrupt INT TERM

  local service name
  for service in "$@"; do
    name="phrasea-lifecycle-$$-${service}"
    LIFECYCLE_CONTAINERS+=("${name}")
    docker compose run --rm -T --name "${name}" "${env_args[@]}" "${service}" su app -c "bin/lifecycle.sh ${phase}" &
    LIFECYCLE_PIDS+=($!)
  done

  local status=0
  local pid
  for pid in "${LIFECYCLE_PIDS[@]}"; do
    wait "${pid}" || status=$?
  done
  LIFECYCLE_PIDS=()

  lifecycle_remove_containers
  trap - INT TERM

  return ${status}
}

# execute a shell command in a container defined in docker-compose.yml
function exec_container() {
  docker compose exec -T "$1" sh -c "$2"
}

function exec_container_as() {
  docker compose exec -T "$1" su "$3" -c "$2"
}

function run_container() {
  docker compose run --rm -T "$1" sh -c "$2"
}

function run_container_as() {
  docker compose run --rm -T "$1" su "$3" -c "$2"
}

function create_db() {
  exec_container db "psql -U \"${POSTGRES_USER}\" -tc \"SELECT 1 FROM pg_database WHERE datname = '$1'\" | grep -q 1 || psql -U \"${POSTGRES_USER}\" -c \"CREATE DATABASE $1\""
}
