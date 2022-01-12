#!/bin/sh

# Export env vars from a file if their are not defined yet
# Usage: export_env_from_file "path/to/env.file"
function export_env_from_file {
    if [ ! -f "$1" ]; then
        return
    fi

    while read -r line || [[ -n "$line" ]];
    do
      if printf '%s\n' "$line" | grep -q -e '^\s*[^#;].*='; then
        varname=$(printf '%s\n' "$line" | sed -e 's/=.*//')
        varvalue=$(printf '%s\n' "$line" | sed -e 's/^[^=]*=//')

        # Read value of current variable if exists as Environment variable
        value=$(printf '%s\n' "${!varname}")
        # Otherwise use value from .env file
        [[ -z $value ]] && value=${varvalue}

        eval $(echo "export ${varname}=$value")
      fi
    done < "$1"
}

# Export env vars from defaults
# Defined env vars take precedence, then env.local, then .env
# Usage: load-env
function load-env {
    if [ ! -f ".env" ]; then
      >&2 echo ".env file not found at $(pwd)"
      exit 1
    fi
    export_env_from_file "env.local"
    export_env_from_file ".env"
}

# execute a shell commmand in a container defined in docker-compose.yml
function exec_container() {
    docker-compose exec -T "$1" sh -c "$2"
}

function exec_container_as() {
    docker-compose exec -T "$1" su "$3" sh -c "$2"
}

function create_db() {
    exec_container db "psql -U \"${POSTGRES_USER}\" -tc \"SELECT 1 FROM pg_database WHERE datname = '$1'\" | grep -q 1 || psql -U \"${POSTGRES_USER}\" -c \"CREATE DATABASE $1\""
}
