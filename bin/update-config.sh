#!/bin/bash


if [ ! -f configs/config.json ]; then
    echo "Creating default config"
    echo "{}" > configs/config.json
fi

RESULT=$(docker run \
    --rm \
    --volume "$(pwd)/configs:/data" \
    boxboat/config-merge \
        -f json /data/config.dist.json /data/config.json)

if [ "$?" == "0" ]; then
    echo "Merge your configuration with dist"
    echo "${RESULT}" > configs/config.json
fi
