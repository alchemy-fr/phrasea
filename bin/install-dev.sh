#!/bin/bash

. "bin/functions.sh"

load-env

. "bin/vars.sh"

set -e

function installComposer() {
    echo "Installing composer in $1..."
    docker-compose run --rm $1 su app sh -c "composer install"
    echo "Done."
    echo ""
    echo ""
}

function installNodeModulesSuNode() {
    echo "Installing node modules in $1..."
    docker-compose run --rm $1 su node sh -c "yarn install"
    echo "Done."
    echo ""
    echo ""
}

function installNodeModules() {
    echo "Installing node modules in $1..."
    docker-compose run --rm $1 yarn install
    echo "Done."
    echo ""
    echo ""
}

installComposer auth-api-php
installComposer notify-api-php
installComposer databox-api-php
installComposer uploader-api-php
installComposer expose-api-php

installNodeModulesSuNode expose-client-dev
installNodeModulesSuNode uploader-client-dev
installNodeModulesSuNode databox-client-dev

installNodeModules databox-indexer
