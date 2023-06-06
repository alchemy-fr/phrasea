ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}php-fpm-base:${BASE_TAG} AS api-php

ENV IMAGICK_VERSION=3.7.0

RUN apk add --no-cache \
        perl \
        freetype \
    	imagemagick \
        libjpeg-turbo \
        libpng \
        libwebp \
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
    && pecl install \
        imagick-${IMAGICK_VERSION} \
    && pecl clear-cache \
    && docker-php-ext-enable \
        imagick \
    && apk del build-essentials

# Warm up composer cache for faster builds
COPY docker/caching/composer.* ./
RUN composer install --prefer-dist --no-dev --no-progress --classmap-authoritative --no-interaction --no-scripts \
    && rm -rf vendor composer.*
# End warm up

COPY . .

RUN mkdir -p var/cache var/logs var/sessions \
    && composer install --prefer-dist --no-dev --no-progress --classmap-authoritative --no-interaction \
    && composer clear-cache \
    && chown -R app: .

ADD https://letsencrypt.org/certs/staging/letsencrypt-stg-root-x1.pem /usr/local/share/ca-certificates/letsencrypt-root-ca.pem
RUN chmod 644 /usr/local/share/ca-certificates/letsencrypt-root-ca.pem \
    && update-ca-certificates

ENTRYPOINT ["/srv/app/docker/php-entrypoint.sh"]

CMD ["php-fpm"]

FROM ${REGISTRY_NAMESPACE}nginx-fpm-base:${BASE_TAG} AS api-nginx

COPY --from=api-php /srv/app/public /srv/app/public
ENV FPM_HOSTNAME=databox-api-php


FROM api-php as worker

ENV PHP_MEMORY_LIMIT=1G

RUN apk add --no-cache \
        supervisor \
        perl \
    && mkdir -p /var/log/supervisor \
    && chown -R app: /var/log/supervisor \
    && docker-php-ext-install pcntl

COPY docker/supervisor/* /etc/supervisor.d/

CMD ["/bin/sh", "-c", "/usr/bin/supervisord -n"]
