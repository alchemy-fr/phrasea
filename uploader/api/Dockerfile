FROM php:7.4.4-fpm-alpine3.11 AS uploader-api-php

RUN apk add --no-cache --virtual .persistent-deps \
		git \
		gettext \
		postgresql-dev \
		icu-libs \
		zlib \
		libzip

ENV APCU_VERSION=5.1.18 \
    XDEBUG_VERSION=2.9.4 \
    APP_ENV=prod

RUN set -eux \
	&& apk add --no-cache --virtual .build-deps \
		$PHPIZE_DEPS \
		icu-dev \
		zlib-dev \
		libzip-dev \
	&& docker-php-ext-install \
		intl \
		zip \
		sockets \
		bcmath \
		pdo \
		pdo_pgsql \
	&& pecl install \
		apcu-${APCU_VERSION} \
		xdebug-${XDEBUG_VERSION} \
	&& docker-php-ext-enable --ini-name 20-apcu.ini apcu \
	&& docker-php-ext-enable --ini-name 05-opcache.ini opcache \
	&& apk del .build-deps \
    && curl -L https://download.newrelic.com/php_agent/release/newrelic-php5-9.19.0.309-linux-musl.tar.gz | tar -C /tmp -zx \
    && export NR_INSTALL_USE_CP_NOT_LN=1 \
    && export NR_INSTALL_SILENT=1 \
    && /tmp/newrelic-php5-*/newrelic-install install \
    && rm -rf /tmp/newrelic-php5-* /tmp/nrinstall* \
    && rm $PHP_INI_DIR/conf.d/newrelic.ini

COPY --from=composer:2.0.3 /usr/bin/composer /usr/bin/composer
COPY docker/app/conf.d/symfony.ini /usr/local/etc/php-fpm.d/

RUN ln -s $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini \
    # Increase process-timeout for "composer test" which downloads phpunit
    && composer config --global process-timeout 2000 \
    && adduser -D -u 1000 app \
    && printf "\nuser = app\ngroup = app\n" >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && printf "\nrequest_terminate_timeout = 600\n" >> /usr/local/etc/php-fpm.d/large-upload.conf

WORKDIR /srv/app

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

FROM nginx:1.15.11-alpine AS uploader-api-nginx

COPY --from=uploader-api-php /srv/app/public /srv/app/public
COPY docker/nginx/entrypoint.sh /entrypoint.sh
COPY docker/nginx/tpl /etc/nginx/tpl

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]

FROM uploader-api-php as uploader-worker

RUN apk add --no-cache \
        supervisor \
    && mkdir -p /var/log/supervisor \
    && chown -R app: /var/log/supervisor

COPY docker/supervisor/* /etc/supervisor.d/

ENTRYPOINT []

CMD ["/bin/sh", "-c", "su app -c 'bin/console cache:clear' && /usr/bin/supervisord -n"]
