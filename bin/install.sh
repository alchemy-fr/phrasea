#!/bin/bash

set -e

docker-compose -f docker-compose.yml run --rm auth_php /bin/sh -c \
    "bin/console doctrine:database:create; bin/console doctrine:schema:create"
