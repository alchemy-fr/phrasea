FROM php:8.5.1-fpm-alpine3.22

RUN apk add --no-cache --virtual .persistent-deps \
        fcgi \
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
    APCU_VERSION=5.1.28 \
    AMQP_VERSION=2.2.0 \
    XDEBUG_VERSION=3.5.0 \
    REDIS_VERSION=6.3.0

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

COPY --from=composer:2.9.3 /usr/bin/composer /usr/bin/composer

RUN ln -s $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini \
    # Increase process-timeout for "composer test" which downloads phpunit
    && composer config --global process-timeout 2000 \
    && adduser -D -u 1000 app \
    && printf "\nuser = app\ngroup = app\n" >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && wget -O /usr/local/bin/php-fpm-healthcheck https://raw.githubusercontent.com/renatomefi/php-fpm-healthcheck/v0.5.0/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck \
    && printf "pm.status_path = /status\n" >> /usr/local/etc/php-fpm.d/zz-docker.conf \
    && mkdir -p /docker/entrypoint.d \
      /docker/app/conf.d \
      /etc/supervisor.d \
      /docker/worker \
      /etc/app

COPY conf.d/* /docker/app/conf.d/
COPY entrypoint.sh /docker/entrypoint.sh
COPY entrypoint.d/*.sh /docker/entrypoint.d/
COPY supervisor/* /etc/supervisor.d/
COPY worker/* /docker/worker/

ENV SUPERVISOR_START_SECS=2 \
    SUPERVISOR_START_RETRIES=10 \
    SUPERVISOR_STOP_WAIT_SECS=20

WORKDIR /srv/app

RUN chown -R app:app .

ENTRYPOINT ["/docker/entrypoint.sh"]

CMD ["php-fpm"]

HEALTHCHECK --start-interval=2s --start-period=30s --retries=50 --interval=60s --timeout=3s \
    CMD php-fpm-healthcheck || exit 1
