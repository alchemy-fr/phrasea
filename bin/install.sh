#!/bin/bash

set -ex

export APP_ENV=prod

docker-compose up -d \
    && docker-compose -f docker-compose.yml run --rm auth_php /bin/sh -c \
        "bin/console doctrine:database:create; bin/console doctrine:schema:create" \
    && docker-compose -f docker-compose.yml run --rm upload_php /bin/sh -c \
        "bin/console rabbitmq:setup-fabric"
