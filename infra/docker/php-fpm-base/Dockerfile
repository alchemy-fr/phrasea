FROM php:8.2.4-fpm-alpine3.17

RUN apk add --no-cache --virtual .persistent-deps \
		git \
		icu-libs \
		libzip \
		postgresql-dev \
		zlib \
    	rabbitmq-c-dev \
        gettext \
        linux-headers \
        perl

ENV APP_ENV=prod \
    PHP_MEMORY_LIMIT=132M \
    APCU_VERSION=5.1.22 \
    AMQP_VERSION=1.11.0 \
    XDEBUG_VERSION=3.2.1 \
    REDIS_VERSION=5.3.7

RUN set -eux; \
	apk add --no-cache --virtual .build-deps \
		$PHPIZE_DEPS \
		icu-dev \
		libzip-dev \
		zlib-dev \
	; \
	\
	docker-php-ext-install \
		intl \
		zip \
		sockets \
		bcmath \
		pdo \
		pdo_pgsql \
	; \
	pecl install \
		apcu-${APCU_VERSION} \
		amqp-${AMQP_VERSION} \
		redis-${REDIS_VERSION} \
		xdebug-${XDEBUG_VERSION} \
	; \
	pecl clear-cache; \
	docker-php-ext-enable \
		apcu \
		amqp \
		opcache \
		redis \
	; \
	\
	runDeps="$( \
		scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
			| tr ',' '\n' \
			| sort -u \
			| awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
	)"; \
	apk add --no-cache --virtual .api-phpexts-rundeps $runDeps; \
	apk del .build-deps

RUN export NR_INSTALL_USE_CP_NOT_LN=1 \
	&& export NR_INSTALL_SILENT=1 \
    && NR_FILE=$(curl --silent https://download.newrelic.com/php_agent/release/ | grep -o 'href=".\+newrelic-php5-.\+musl.\+">' | sed 's/href="\/php_agent\/release\///;s/">//') \
    && curl -L https://download.newrelic.com/php_agent/release/${NR_FILE} | tar -C /tmp -zx \
    && /tmp/newrelic-*/newrelic-install install \
	&& rm -rf /tmp/newrelic-* /tmp/nrinstall* \
	&& rm $PHP_INI_DIR/conf.d/newrelic.ini

COPY --from=composer:2.0.3 /usr/bin/composer /usr/bin/composer

RUN ln -s $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini \
    # Increase process-timeout for "composer test" which downloads phpunit
    && composer config --global process-timeout 2000 \
    && adduser -D -u 1000 app \
    && printf "\nuser = app\ngroup = app\n" >> /usr/local/etc/php-fpm.d/zz-docker.conf

WORKDIR /srv/app

CMD ["true"]
