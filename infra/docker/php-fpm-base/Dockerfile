FROM php:7.4.33-fpm-alpine3.16

# PHP 7.4 is EOL: rebuild it with the upstream backport of CVE-2026-109618 / CVE-2026-109619
# (php/php-src commit 712e60f9edfd03f0960fc4fc3c2c375f42b90aa2, branch PHP-7.4-CVE-2026-109618-109619).
# The patched sources are repacked so that docker-php-ext-install builds against them.
COPY php-7.4-CVE-2026-109618-109619.patch /usr/src/
RUN set -eux; \
    apk add --no-cache --virtual .php-rebuild-deps \
        $PHPIZE_DEPS \
        argon2-dev \
        coreutils \
        curl-dev \
        gnu-libiconv-dev \
        libsodium-dev \
        libxml2-dev \
        linux-headers \
        oniguruma-dev \
        openssl-dev \
        patch \
        readline-dev \
        sqlite-dev \
    ; \
    rm -vf /usr/include/iconv.h; \
    ln -sv /usr/include/gnu-libiconv/iconv.h /usr/include/iconv.h; \
    docker-php-source extract; \
    cd /usr/src/php; \
    patch -p1 < /usr/src/php-7.4-CVE-2026-109618-109619.patch; \
    export CFLAGS="$PHP_CFLAGS" CPPFLAGS="$PHP_CPPFLAGS" LDFLAGS="$PHP_LDFLAGS"; \
    eval "./configure $(php-config --configure-options)"; \
    make -j "$(nproc)"; \
    find -type f -name '*.a' -delete; \
    make install; \
    find /usr/local -type f -perm '/0111' -exec sh -euxc 'strip --strip-all "$@" || :' -- '{}' +; \
    make clean; \
    cd /usr/src; \
    rm php.tar.xz; \
    tar -cJf php.tar.xz php; \
    docker-php-source delete; \
    rm /usr/src/php-7.4-CVE-2026-109618-109619.patch; \
    apk del --no-network .php-rebuild-deps; \
    php --version

RUN apk add --no-cache --virtual .persistent-deps \
        git \
        gettext \
        postgresql-dev \
        icu-libs \
        zlib \
        libzip \
        perl

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
