FROM php:7.4.4-fpm-alpine3.11

RUN apk add --no-cache --virtual .persistent-deps \
		git \
		gettext \
		postgresql-dev \
		icu-libs \
		zlib \
		libzip

ENV APCU_VERSION=5.1.18 \
    XDEBUG_VERSION=2.9.4 \
    APP_ENV=prod \
    PHP_MEMORY_LIMIT=132M

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
    && curl -L https://download.newrelic.com/php_agent/archive/9.20.0.310/newrelic-php5-9.20.0.310-linux-musl.tar.gz | tar -C /tmp -zx \
    && export NR_INSTALL_USE_CP_NOT_LN=1 \
    && export NR_INSTALL_SILENT=1 \
    && /tmp/newrelic-php5-*/newrelic-install install \
    && rm -rf /tmp/newrelic-php5-* /tmp/nrinstall* \
    && rm $PHP_INI_DIR/conf.d/newrelic.ini

COPY --from=composer:2.0.3 /usr/bin/composer /usr/bin/composer

RUN ln -s $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini \
    # Increase process-timeout for "composer test" which downloads phpunit
    && composer config --global process-timeout 2000 \
    && adduser -D -u 1000 app \
    && printf "\nuser = app\ngroup = app\n" >> /usr/local/etc/php-fpm.d/zz-docker.conf

WORKDIR /srv/app

CMD ["true"]