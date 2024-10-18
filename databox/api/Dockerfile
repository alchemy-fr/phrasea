ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}php-fpm-base:${BASE_TAG} AS api-php

ENV IMAGICK_PHP83_FIX_COMMIT=9df92616f577e38625b96b7b903582a46c064739
# IMAGICK_VERSION=3.7.0

RUN apk add --no-cache \
        perl \
        freetype \
    	imagemagick \
        libjpeg-turbo \
        libpng \
        libwebp \
        ffmpeg \
        libreoffice \
    && apk add --no-cache --virtual build-essentials \
        g++ \
        make \
        automake \
        autoconf \
        libpng-dev \
        libwebp-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        imagemagick-dev \
    && curl -L https://github.com/remicollet/imagick/archive/${IMAGICK_PHP83_FIX_COMMIT}.zip -o /tmp/imagick-issue-php83.zip  \
        && unzip /tmp/imagick-issue-php83.zip -d /tmp \
        && pecl install /tmp/imagick-${IMAGICK_PHP83_FIX_COMMIT}/package.xml \
#    && pecl install \
#        imagick-${IMAGICK_VERSION} \
    && pecl clear-cache \
    && docker-php-ext-install \
        exif \
    && docker-php-ext-enable \
        imagick \
        exif \
    && apk del build-essentials

# Warm up composer cache for faster builds
COPY ./databox/api/docker/caching/composer.* ./
RUN composer install --prefer-dist --no-dev --no-progress --classmap-authoritative --no-interaction --no-scripts \
    && rm -rf vendor composer.*
# End warm up

COPY --chown=app:app lib/php /lib/php
COPY --chown=app:app ./databox/api .

RUN mkdir -p var/cache var/logs var/sessions \
    && composer install --prefer-dist --no-dev --no-progress --classmap-authoritative --no-interaction \
    && composer clear-cache \
    && chown -R app: .

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}

ENTRYPOINT ["/srv/app/docker/php-entrypoint.sh"]

CMD ["php-fpm"]

FROM ${REGISTRY_NAMESPACE}nginx-fpm-base:${BASE_TAG} AS api-nginx

COPY --from=api-php /srv/app/public /srv/app/public
ENV FPM_HOSTNAME=databox-api-php


FROM api-php AS worker

ENV PHP_MEMORY_LIMIT=1G \
    WORKER_PRIORITIES="p1 p2" \
    WORKER_TIME_LIMIT=3600 \
    WORKER_LIMIT=200

RUN apk add --no-cache \
        supervisor \
    && mkdir -p /var/log/supervisor \
    && chown -R app: /var/log/supervisor \
    && docker-php-ext-configure pcntl --enable-pcntl \
    && docker-php-ext-install \
      pcntl

COPY ./databox/api/docker/supervisor/* /etc/supervisor.d/

ENTRYPOINT ["/srv/app/docker/worker/docker-entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-n"]
