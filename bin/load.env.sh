#!/bin/sh

BASEDIR=$(dirname $0)

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
done < "$BASEDIR/../.env"

if [ -f "$BASEDIR/../env.local" ]; then
    export $(cat "$BASEDIR/../env.local" | grep -v '#' | xargs)
fi
