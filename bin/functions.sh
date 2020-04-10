#!/bin/sh

BASEDIR=$(dirname $0)

# Export a value from a file if it exists in the file
# The environment var take precedence
# Usage : export_value_from_file "VAR_NAME" "path/of/env.file"
function export_value_from_file {

  chunk=$(cat $2 | grep $1 | grep "^[^#;]")
  varname=$(echo $chunk | sed -e 's/=.*//')
  toto=$1
  if [[ $varname == "$1" ]]; then
     varvalue=$(echo $chunk | sed -e 's/^[^=]*=//')
     env_value=$(eval echo \$$toto)
     [[ -z $env_value ]] && eval export $varname=$varvalue
  fi
}

# Search for a variable in .env and in env.local and export it
# The environment var take precedence
# Usage : export_from_env_file "VAR_NAME"
function export_from_env_file () {
    export_value_from_file $1 $BASEDIR/../.env
    export_value_from_file $1 $BASEDIR/../env.local

}

# Run docker-compose depending on the APP_ENV value
# If APP_ENV = PROD, then only use docker-compose.yml file
function d-c {
    if [ ${APP_ENV} == "prod" ]; then
        docker-compose -f docker-compose.yml "$@"
    else
        docker-compose "$@"
    fi
}

# Make an "export" on every vars in ../env.local file
# /!\ The behavior is not the same than ./env : it override environment vars
function load-env-local {
    if [ -f "$BASEDIR/../env.local" ]; then
        set -a
        . "$BASEDIR/../env.local"
        set +a
    fi
}

# execute a shell commmand in a container defined in docker-compose.yml
function exec_container() {
    d-c exec -T "$1" sh -c "$2"
}

