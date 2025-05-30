ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}php-fpm-base:${BASE_TAG} AS api-php

USER app

# Warm up composer cache for faster builds
COPY ./expose/api/docker/caching/composer.* ./
RUN composer install --prefer-dist --no-dev --no-progress --classmap-authoritative --no-interaction --no-scripts \
    && rm -rf vendor composer.*
# End warm up

COPY --chown=app:app lib/php /lib/php
COPY --chown=app:app ./expose/api .

RUN mkdir -p var/cache var/logs var/sessions \
    && composer install --prefer-dist --no-dev --no-progress --classmap-authoritative --no-interaction \
    && composer clear-cache

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}

USER root


FROM ${REGISTRY_NAMESPACE}nginx-cache-purge:${BASE_TAG} AS api-nginx

COPY --from=api-php /srv/app/public /srv/app/public
COPY ./expose/api/docker/nginx/entrypoint.sh /entrypoint.sh
COPY ./expose/api/docker/nginx/tpl /etc/nginx/tpl

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]


FROM api-php AS worker

ENV PHP_MEMORY_LIMIT=1G \
    WORKER_PRIORITIES=p1 \
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
