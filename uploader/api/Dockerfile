ARG BASE_TAG=latest
ARG REGISTRY_NAMESPACE
FROM ${REGISTRY_NAMESPACE}php-fpm-base:${BASE_TAG} AS api-php

RUN printf "\nrequest_terminate_timeout = 600\n" >> /usr/local/etc/php-fpm.d/large-upload.conf

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

ENTRYPOINT ["/srv/app/docker/fpm-entrypoint.sh"]

CMD ["php-fpm"]

FROM ${REGISTRY_NAMESPACE}nginx-fpm-base:${BASE_TAG} AS api-nginx

COPY --from=api-php /srv/app/public /srv/app/public
ENV FPM_HOSTNAME=uploader-api-php

FROM api-php as worker

RUN apk add --no-cache \
        supervisor \
    && mkdir -p /var/log/supervisor \
    && chown -R app: /var/log/supervisor \
    && docker-php-ext-install pcntl

COPY docker/supervisor/* /etc/supervisor.d/

ENTRYPOINT []

CMD ["/bin/sh", "-c", "su app -c 'bin/console cache:clear' && /usr/bin/supervisord -n"]
