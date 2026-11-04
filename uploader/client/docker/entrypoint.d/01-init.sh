#!/bin/sh

/docker/configurator/get-config.sh

qjs /docker/configurator/generate-env.mjs ./
