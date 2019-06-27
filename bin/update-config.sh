#!/bin/bash

BASEDIR="$(dirname $0)/../configs"

if [ ! -f ${BASEDIR}/config.json ]; then
    echo "Creating default config"
    echo "{}" > ${BASEDIR}/config.json
fi

RESULT=$(docker run \
    --rm \
    --volume "$(pwd)/${BASEDIR}:/data" \
    boxboat/config-merge \
        -f json /data/config.dist.json /data/config.json)

if [ "$?" == "0" ]; then
    echo "Merge your configuration with dist"
    echo "${RESULT}" > ${BASEDIR}/config.json
fi
