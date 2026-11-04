ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}php-fpm-base:${BASE_TAG} AS api-php

USER app

# Warm up the Composer cache with the dependencies of the lock, without the
# local packages, so that changes in lib/php or in the app keep this layer.
COPY --chown=app:app ./uploader/api/composer.json ./uploader/api/composer.lock ./
RUN composer-strip-path-packages \
    && composer install --prefer-dist --no-dev --no-progress --no-interaction --no-scripts --no-plugins --no-autoloader \
    && rm -rf vendor composer.*

COPY ./lib/bash/configurator /docker/configurator
COPY --chown=app:app lib/php /lib/php
COPY --chown=app:app ./uploader/api .

RUN mkdir -p var/cache var/logs var/sessions \
    && composer install --prefer-dist --no-dev --no-progress --classmap-authoritative --no-interaction \
    && composer clear-cache \
    && bin/console alchemy:core:documentation:dump /srv/app/doc

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}

USER root


FROM ${REGISTRY_NAMESPACE}nginx-fpm-base:${BASE_TAG} AS api-nginx

COPY --from=api-php /srv/app/public /srv/app/public

ENV FPM_HOSTNAME=uploader-api-php

FROM api-php AS worker

ENV PHP_MEMORY_LIMIT=1G \
    WORKER_PRIORITIES="p1 p2 p3" \
    WORKER_TIME_LIMIT=3600 \
    WORKER_LIMIT=200 \
    IS_WORKER=1

RUN apk add --no-cache \
        supervisor \
    && mkdir -p /var/log/supervisor \
    && chown -R app: /var/log/supervisor \
    && docker-php-ext-configure pcntl --enable-pcntl \
    && docker-php-ext-install \
      pcntl

CMD ["/usr/bin/supervisord", "-n"]

HEALTHCHECK --start-interval=1s --start-period=90s --retries=100 --interval=60s --timeout=1s \
    CMD ash -c "[ -f /docker/worker-ready ]"
