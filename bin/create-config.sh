#!/bin/bash

if [ ! -f configs/config.json ]; then
    echo "Creating default config"
    cp configs/config.dist.json configs/config.json
fi
