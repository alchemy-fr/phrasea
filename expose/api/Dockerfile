ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}php-fpm-base:${BASE_TAG} AS api-php

ADD https://letsencrypt.org/certs/staging/letsencrypt-stg-root-x1.pem /usr/local/share/ca-certificates/letsencrypt-root-ca.pem
RUN chmod 644 /usr/local/share/ca-certificates/letsencrypt-root-ca.pem \
    && update-ca-certificates

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

ARG SENTRY_RELEASE
ENV SENTRY_RELEASE=${SENTRY_RELEASE}

ENTRYPOINT ["/srv/app/docker/php-entrypoint.sh"]

CMD ["php-fpm"]

FROM ${REGISTRY_NAMESPACE}nginx-cache-purge:${BASE_TAG} AS api-nginx

COPY --from=api-php /srv/app/public /srv/app/public
COPY docker/nginx/entrypoint.sh /entrypoint.sh
COPY docker/nginx/tpl /etc/nginx/tpl

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]


FROM api-php as worker

RUN apk add --no-cache \
        supervisor \
    && mkdir -p /var/log/supervisor \
    && chown -R app: /var/log/supervisor \
    && docker-php-ext-install pcntl

COPY docker/supervisor/* /etc/supervisor.d/

CMD ["/bin/sh", "-c", "/usr/bin/supervisord -n"]
