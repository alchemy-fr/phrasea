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

# execute a shell commmand in a container defined in docker-compose.yml
function exec_container() {
  docker compose exec -T "$1" sh -c "$2"
}

function exec_container_as() {
  docker compose exec -T "$1" su "$3" sh -c "$2"
}

function create_db() {
  exec_container db "psql -U \"${POSTGRES_USER}\" -tc \"SELECT 1 FROM pg_database WHERE datname = '$1'\" | grep -q 1 || psql -U \"${POSTGRES_USER}\" -c \"CREATE DATABASE $1\""
}
