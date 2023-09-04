ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}php-fpm-base:${BASE_TAG} AS api-php

WORKDIR /usr/app

ENV APP_ENV=prod

COPY . .

RUN composer install --prefer-dist --no-dev --no-progress --classmap-authoritative --no-interaction \
    && composer clear-cache \
    && chown -R app: .

ENTRYPOINT ["bin/console"]

CMD ["configure"]
