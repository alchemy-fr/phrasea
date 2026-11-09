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
  docker compose exec -T "$1" su "$3" sh -c "$2"
}

function run_container() {
  docker compose run --rm -T "$1" sh -c "$2"
}

function run_container_as() {
  docker compose run --rm -T "$1" su "$3" sh -c "$2"
}

function create_db() {
  exec_container db "psql -U \"${POSTGRES_USER}\" -tc \"SELECT 1 FROM pg_database WHERE datname = '$1'\" | grep -q 1 || psql -U \"${POSTGRES_USER}\" -c \"CREATE DATABASE $1\""
}
