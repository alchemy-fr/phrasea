ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}php-fpm-base:${BASE_TAG} AS app

# Imagick only serves the fixtures (alchemy/core-bundle ImageUrlFaker); the
# deny-by-default policy of databox applies all the same.
ENV IMAGICK_VERSION=3.8.1 \
    IMAGEMAGICK_POLICY_VERSION=7

RUN apk add --no-cache \
    	imagemagick \
        freetype \
        libjpeg-turbo \
    && apk add --no-cache --virtual build-essentials \
        g++ \
        make \
        automake \
        autoconf \
        libjpeg-turbo-dev \
        freetype-dev \
        imagemagick-dev \
    && pecl clear-cache \
    && pecl install \
        imagick-${IMAGICK_VERSION} \
    && docker-php-ext-enable \
        imagick \
    && apk del build-essentials \
    && pecl clear-cache

USER app

# Warm up the Composer cache with the dependencies of the lock, without the
# local packages, so that changes in lib/php or in the app keep this layer.
COPY --chown=app:app ./expose/api/composer.json ./expose/api/composer.lock ./
RUN composer-strip-path-packages \
    && composer install --prefer-dist --no-dev --no-progress --no-interaction --no-scripts --no-plugins --no-autoloader \
    && rm -rf vendor composer.*

COPY ./lib/bash/configurator /docker/configurator
COPY --chown=app:app lib/php /lib/php
COPY --chown=app:app ./expose/api .

RUN mkdir -p var/cache var/logs var/sessions \
    && composer install --prefer-dist --no-dev --no-progress --classmap-authoritative --no-interaction \
    && composer clear-cache \
    && bin/console alchemy:core:documentation:dump /srv/app/doc

COPY ./databox/api/docker/entrypoint.d/01-imagick.sh /docker/entrypoint.d/

USER root


# SENTRY_RELEASE, PHRASEA_VERSION and PHRASEA_COMMIT change on every commit:
# they are set last in each final stage, so that the cached steps above stay
# valid.
FROM app AS api-php

ARG SENTRY_RELEASE
ARG PHRASEA_VERSION=dev
ARG PHRASEA_COMMIT=
ENV SENTRY_RELEASE=${SENTRY_RELEASE} \
    PHRASEA_VERSION=${PHRASEA_VERSION:-dev} \
    PHRASEA_COMMIT=${PHRASEA_COMMIT}


FROM ${REGISTRY_NAMESPACE}nginx-cache-purge:${BASE_TAG} AS api-nginx

COPY --from=app /srv/app/public /srv/app/public
COPY ./expose/api/docker/nginx/entrypoint.sh /entrypoint.sh
COPY ./expose/api/docker/nginx/tpl /etc/nginx/tpl

ENV FPM_HOSTNAME=expose-api-php

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]


FROM app AS worker

ENV PHP_MEMORY_LIMIT=1G \
    WORKER_PRIORITIES=p1 \
    WORKER_TIME_LIMIT=3600 \
    WORKER_LIMIT=1000 \
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

ARG SENTRY_RELEASE
ARG PHRASEA_VERSION=dev
ARG PHRASEA_COMMIT=
ENV SENTRY_RELEASE=${SENTRY_RELEASE} \
    PHRASEA_VERSION=${PHRASEA_VERSION:-dev} \
    PHRASEA_COMMIT=${PHRASEA_COMMIT}
