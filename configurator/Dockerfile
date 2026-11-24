ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}php-fpm-base:${BASE_TAG} AS api-php

WORKDIR /srv/app

COPY --chown=app:app ./.env ./.env.default
COPY --chown=app:app ./configurator .
COPY --chown=app:app lib/php /srv/lib/php

# The runtime memory limit is too low for the cache warmup and the documentation dump.
RUN export PHP_MEMORY_LIMIT=-1 \
    && mkdir -p var/cache var/logs var/sessions \
    && composer install --prefer-dist --no-dev --no-progress --classmap-authoritative --no-interaction \
    && composer clear-cache \
    && bin/console alchemy:core:documentation:dump /srv/app/doc \
    && chown -R app: .

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}

ENTRYPOINT ["/srv/app/docker/php-entrypoint.sh"]

CMD ["bin/console", "configure"]
