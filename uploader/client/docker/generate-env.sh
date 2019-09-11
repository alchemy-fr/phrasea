#!/bin/bash

BASEDIR=$(dirname $0)
INDEX_DIR=$1

ENV_CONFIG=/configs/__env.json
ENV_FILE=./env-config.js

rm -rf ${ENV_CONFIG}
touch ${ENV_CONFIG}
# Add assignment
echo '{"_env_":{' >> ${ENV_CONFIG}

# Read each line in .env file
# Each line represents key=value pairs
N=0

while read -r line || [[ -n "$line" ]];
do
  # Split env variables by character `=`
  if printf '%s\n' "$line" | grep -q -e '='; then
    varname=$(printf '%s\n' "$line" | sed -e 's/=.*//')
    varvalue=$(printf '%s\n' "$line" | sed -e 's/^[^=]*=//')
  fi

  # Read value of current variable if exists as Environment variable
  value=$(printf '%s\n' "${!varname}")
  # Otherwise use value from .env file
  [[ -z $value ]] && value=${varvalue}

  if [ $N -gt 0 ]; then
    echo "," >> ${ENV_CONFIG}
  fi
  N=$(( $N + 1 ))

  # Append configuration property to JS file
  echo -n "  \"$varname\": \"$value\"" >> ${ENV_CONFIG}
done < "$BASEDIR/../.env"

echo "}}" >> ${ENV_CONFIG}

# Recreate config file
rm -rf ${ENV_FILE}
touch ${ENV_FILE}
echo -n "window.config = " >> ${ENV_FILE}
jq -s 'reduce .[] as $item ({}; . * $item)' /configs/*.json >> ${ENV_FILE}
echo ";" >> ${ENV_FILE}

HASH=`md5sum ${ENV_FILE} | awk '{ print $1 }'`
rm -f ./env-config.*.js
ENV_FILE_HASHED=./env-config.${HASH}.js
mv ${ENV_FILE} ${ENV_FILE_HASHED}

if [ ${INDEX_DIR} == "./" ]; then
    # for production build only
    if [ ! -f "${INDEX_DIR}/index.html.bk" ]; then
        cp ${INDEX_DIR}/index.html ${INDEX_DIR}/index.html.bk
    fi
    cp ${INDEX_DIR}/index.html.bk ${INDEX_DIR}/index.tpl.html
fi

cp ${INDEX_DIR}/index.tpl.html ${INDEX_DIR}/index.html
sed -i -e "s/__TPL_HASH__/${HASH}/g" ${INDEX_DIR}/index.html
