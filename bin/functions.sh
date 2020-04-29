#!/bin/sh

BASEDIR=$(dirname $0)

# Export env vars from a file if their are not defined yet
# Usage: export_env_from_file "path/to/env.file"
function export_env_from_file {
    if [ ! -f "$1" ]; then
        return
    fi

    while read -r line || [[ -n "$line" ]];
    do
      if printf '%s\n' "$line" | grep -q -e '='; then
        varname=$(printf '%s\n' "$line" | sed -e 's/=.*//')
        varvalue=$(printf '%s\n' "$line" | sed -e 's/^[^=]*=//')
      fi

      # Read value of current variable if exists as Environment variable
      value=$(printf '%s\n' "${!varname}")
      # Otherwise use value from .env file
      [[ -z $value ]] && value=${varvalue}

      eval $(echo "export ${varname}=$value")
    done < "$1"
}

# Export env vars from defaults
# Defined env vars take precedence, then env.local, then .env
# Usage: load-env
function load-env {
    export_env_from_file "$BASEDIR/../env.local"
    export_env_from_file "$BASEDIR/../.env"
}

# Run docker-compose depending on the APP_ENV value
# If APP_ENV = PROD, then only use docker-compose.yml file
function d-c {
    if [ "${APP_ENV}" == "prod" ]; then
        docker-compose -f docker-compose.yml "$@"
    else
        docker-compose "$@"
    fi
}

# execute a shell commmand in a container defined in docker-compose.yml
function exec_container() {
    d-c exec -T "$1" sh -c "$2"
}

function create_db() {
    exec_container db "psql -U \"${POSTGRES_USER}\" -tc \"SELECT 1 FROM pg_database WHERE datname = '$1'\" | grep -q 1 || psql -U \"${POSTGRES_USER}\" -c \"CREATE DATABASE $1\""
}
