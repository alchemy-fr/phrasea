ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}php-fpm-base:${BASE_TAG} AS api-php

ENV IMAGICK_VERSION=3.8.1 \
    IMAGEMAGICK_POLICY_VERSION=7 \
    IMAGEMAGICK_POLICY_WIDTH=48KP \
    IMAGEMAGICK_POLICY_MAP=2048MiB \
    IMAGEMAGICK_POLICY_AREA=4096MB \
    IMAGEMAGICK_POLICY_DISK=6GiB \
    IMAGEMAGICK_POLICY_TEMPORARY_PATH=/tmp

RUN apk add --no-cache \
    	imagemagick \
        imagemagick-heic \
        imagemagick-webp \
        ffmpeg \
        freetype \
        ghostscript \
        libheif \
        libjpeg-turbo \
        libpng \
        libreoffice \
        fontconfig \
        ttf-dejavu \
        ttf-liberation \
        font-carlito \
        font-noto \
        font-noto-cjk \
        font-noto-emoji \
        libwebp \
        perl \
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
        openssl-dev \
    && pecl clear-cache \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure ftp --with-ftp-ssl \
    && docker-php-ext-install \
        exif \
        ftp \
        gd \
    && pecl install \
        imagick-${IMAGICK_VERSION} \
    && docker-php-ext-enable \
        imagick \
        exif \
    && apk del build-essentials \
    && pecl clear-cache

USER app

# Warm up the Composer cache with the dependencies of the lock, without the
# local packages, so that changes in lib/php or in the app keep this layer.
COPY --chown=app:app ./databox/api/composer.json ./databox/api/composer.lock ./
RUN composer-strip-path-packages \
    && composer install --prefer-dist --no-dev --no-progress --no-interaction --no-scripts --no-plugins --no-autoloader \
    && rm -rf vendor composer.*

COPY ./lib/bash/configurator /docker/configurator
COPY --chown=app:app lib/php /lib/php
COPY --chown=app:app ./databox/api .

RUN mkdir -p var/cache var/logs var/sessions \
    && composer install --prefer-dist --no-dev --no-progress --classmap-authoritative --no-interaction \
    && composer clear-cache \
    && php -d memory_limit=512M bin/console alchemy:core:documentation:dump /srv/app/doc

COPY ./databox/api/docker/entrypoint.d/*.sh /docker/entrypoint.d/

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}

USER root


FROM ${REGISTRY_NAMESPACE}nginx-fpm-base:${BASE_TAG} AS api-nginx

COPY --from=api-php /srv/app/public /srv/app/public

ENV FPM_HOSTNAME=databox-api-php


FROM api-php AS worker

ENV PHP_MEMORY_LIMIT=1G \
    WORKER_PRIORITIES="p1 p2 task" \
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

# With CONVERTER_URL, LibreOffice, ffmpeg, ffprobe and exiftool run in databox-converter: the files
# go through the volume shared with it (/var/convert, TMPDIR in /var/convert/worker), the
# application runs the convert-exec wrapper of each tool (see doc/tech/Databox/converter.md)
RUN mkdir -p /var/convert /usr/local/libexec/convert \
    && install -d -o app -g app -m 0700 /var/convert/worker \
    && install -d -o app -g app -m 0711 /var/convert/jobs \
    && for tool in soffice ffmpeg ffprobe exiftool; do \
        printf '#!/bin/sh\nexec /usr/local/bin/php /lib/php/rendition-factory/bin/convert-exec %s "$@"\n' "$tool" > /usr/local/libexec/convert/$tool; \
        chmod 0755 /usr/local/libexec/convert/$tool; \
    done \
    && ln -s /lib/php/rendition-factory/bin/convert-exec /usr/local/bin/convert-exec

CMD ["/usr/bin/supervisord", "-n"]

HEALTHCHECK --start-interval=1s --start-period=90s --retries=100 --interval=60s --timeout=1s \
    CMD ash -c "[ -f /docker/worker-ready ]"


# Runs the tools that read untrusted files (LibreOffice, ffmpeg, ffprobe, exiftool) for the workers,
# each job with a uid of its own, without any secret nor access to the data services: no
# application code, no .env (see doc/tech/Databox/converter.md)
FROM php:8.5.12-cli-alpine3.24 AS converter

RUN apk add --no-cache \
        ffmpeg \
        libreoffice \
        fontconfig \
        ttf-dejavu \
        ttf-liberation \
        font-carlito \
        font-noto \
        font-noto-cjk \
        font-noto-emoji \
        perl \
        setpriv \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && docker-php-ext-install pcntl \
    && apk del .build-deps \
    # The uids of the job slots (Server::FIRST_UID, Server::POOL_SIZE): no shell, no home
    && for i in $(seq 1 32); do \
        adduser -D -H -h /nonexistent -s /sbin/nologin -u $((2000 + i)) convert$i; \
    done \
    && mkdir -p /var/convert \
    && install -d -o 1000 -g 1000 -m 0700 /var/convert/worker \
    && install -d -o 1000 -g 1000 -m 0711 /var/convert/jobs

# The exiftool of phpexiftool, whose version the tag classes of the application are generated from
COPY --from=api-php --chown=root:root /srv/app/vendor/exiftool/exiftool/exiftool /opt/exiftool/exiftool
COPY --from=api-php --chown=root:root /srv/app/vendor/exiftool/exiftool/lib /opt/exiftool/lib
COPY --from=api-php --chown=root:root /srv/app/vendor/exiftool/exiftool/arg_files /opt/exiftool/arg_files
COPY lib/php/rendition-factory/src/RemoteExec /opt/convert/src/RemoteExec
COPY lib/php/rendition-factory/bin/convert-server lib/php/rendition-factory/bin/remote-exec-autoload.php /opt/convert/bin/

ENV CONVERTER_MAX_JOBS=4 \
    CONVERTER_MAX_TIMEOUT=3600

WORKDIR /

CMD ["php", "/opt/convert/bin/convert-server"]

HEALTHCHECK --start-interval=1s --start-period=30s --retries=3 --interval=60s --timeout=3s \
    CMD php -r 'exit(@fsockopen("127.0.0.1", 9000) ? 0 : 1);'
