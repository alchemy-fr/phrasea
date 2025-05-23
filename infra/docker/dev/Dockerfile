FROM php:8.3.11-cli-alpine3.20

RUN apk add --no-cache \
		autoconf \
		curl \
		git \
		grep \
		icu-libs \
		libzip \
		mysql-dev \
		postgresql-dev \
		zlib \
    	imagemagick \
    	rabbitmq-c-dev \
        bash \
        ffmpeg \
        freetype \
        g++ \
        gettext \
        ghostscript \
        libjpeg-turbo \
        libpng \
        libreoffice \
        libwebp \
        linux-headers \
        make \
        nano \
        openssh-client \
        perl \
        python3 \
        sudo \
        vim \
        zsh \
    && echo 'export PATH="$PATH:$HOME/.composer/vendor/bin"' >> /etc/zsh/zshrc \
    && mkdir -p /docker/entrypoint.d \
    && git clone https://github.com/robbyrussell/oh-my-zsh.git /bootstrap/.oh-my-zsh \
    && adduser -D -u 1000 app \
    && addgroup -S sudo \
    && addgroup app sudo \
    && mkdir -p /etc/sudoers.d \
    && echo $'%sudo ALL = (ALL) NOPASSWD: ALL \n\
Defaults exempt_group = sudo' > /etc/sudoers.d/nopasswd

ENV APCU_VERSION=5.1.22 \
    AMQP_VERSION=1.11.0 \
    IMAGICK_PHP83_FIX_COMMIT=9df92616f577e38625b96b7b903582a46c064739 \
#    IMAGICK_VERSION=3.7.0 \
    XDEBUG_VERSION=3.4.2 \
    REDIS_VERSION=5.3.7 \
    APP_ENV=dev

RUN set -eux; \
	apk add --no-cache --virtual .build-deps \
		$PHPIZE_DEPS \
		icu-dev \
		libzip-dev \
		zlib-dev \
        g++ \
        make \
        automake \
        autoconf \
        libpng-dev \
        libwebp-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        imagemagick-dev \
	; \
	\
	docker-php-ext-install \
        exif \
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
#        imagick-${IMAGICK_VERSION} \
		redis-${REDIS_VERSION} \
		xdebug-${XDEBUG_VERSION} \
	; \
    curl -L https://github.com/remicollet/imagick/archive/${IMAGICK_PHP83_FIX_COMMIT}.zip -o /tmp/imagick-issue-php83.zip \
        && unzip /tmp/imagick-issue-php83.zip -d /tmp \
        && pecl install /tmp/imagick-${IMAGICK_PHP83_FIX_COMMIT}/package.xml; \
	pecl clear-cache; \
	docker-php-ext-enable \
		apcu \
		amqp \
        imagick \
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

COPY --from=composer:2.5.8 /usr/bin/composer /usr/bin/composer

ENV NODE_VERSION=18.20.3 \
    NODE_OPTIONS=--openssl-legacy-provider

RUN apk add --no-cache \
        libstdc++ \
    && apk add --no-cache --virtual .build-deps \
        curl \
    && ARCH= && alpineArch="$(apk --print-arch)" \
      && case "${alpineArch##*-}" in \
        x86_64) \
          ARCH='x64' \
          CHECKSUM="3cfeaa3805cc424d1be0e281f0161416a99d206dcb589a9ab3647d7a6ab7d5c9" \
          ;; \
        *) ;; \
      esac \
  && if [ -n "${CHECKSUM}" ]; then \
    set -eu; \
    curl -fsSLO --compressed "https://unofficial-builds.nodejs.org/download/release/v$NODE_VERSION/node-v$NODE_VERSION-linux-$ARCH-musl.tar.xz"; \
    echo "$CHECKSUM  node-v$NODE_VERSION-linux-$ARCH-musl.tar.xz" | sha256sum -c - \
      && tar -xJf "node-v$NODE_VERSION-linux-$ARCH-musl.tar.xz" -C /usr/local --strip-components=1 --no-same-owner \
      && ln -s /usr/local/bin/node /usr/local/bin/nodejs; \
  else \
    echo "Building from source" \
    # backup build
    && apk add --no-cache --virtual .build-deps-full \
        binutils-gold \
        g++ \
        gcc \
        gnupg \
        libgcc \
        linux-headers \
        make \
        python3 \
    # gpg keys listed at https://github.com/nodejs/node#release-keys
    && for key in \
      4ED778F539E3634C779C87C6D7062848A1AB005C \
      141F07595B7B3FFE74309A937405533BE57C7D57 \
      94AE36675C464D64BAFA68DD7434390BDBE9B9C5 \
      74F12602B6F1C4E913FAA37AD3A89613643B6201 \
      71DCFD284A79C3B38668286BC97EC7A07EDE3FC1 \
      61FC681DFB92A079F1685E77973F295594EC4689 \
      8FCCA13FEF1D0C2E91008E09770F7A9A5AE15600 \
      C4F0DFFF4E8C1A8236409D08E73BC641CC11F4C8 \
      890C08DB8579162FEE0DF9DB8BEAB4DFCF555EF4 \
      C82FA3AE1CBEDC6BE46B9360C43CEC45C17AB93C \
      DD8F2338BAE7501E3DD5AC78C273792F7D83545D \
      A48C2BEE680E841632CD4E44F07496B3EB3C1762 \
      108F52B48DB57BB0CC439B2997B01419BD92F80A \
      B9E2F5981AA6E0CD28160D9FF13993A75599653C \
    ; do \
      gpg --batch --keyserver hkps://keys.openpgp.org --recv-keys "$key" || \
      gpg --batch --keyserver keyserver.ubuntu.com --recv-keys "$key" ; \
    done \
    && curl -fsSLO --compressed "https://nodejs.org/dist/v$NODE_VERSION/node-v$NODE_VERSION.tar.xz" \
    && curl -fsSLO --compressed "https://nodejs.org/dist/v$NODE_VERSION/SHASUMS256.txt.asc" \
    && gpg --batch --decrypt --output SHASUMS256.txt SHASUMS256.txt.asc \
    && grep " node-v$NODE_VERSION.tar.xz\$" SHASUMS256.txt | sha256sum -c - \
    && tar -xf "node-v$NODE_VERSION.tar.xz" \
    && cd "node-v$NODE_VERSION" \
    && ./configure \
    && make -j$(getconf _NPROCESSORS_ONLN) V= \
    && make install \
    && apk del .build-deps-full \
    && cd .. \
    && rm -Rf "node-v$NODE_VERSION" \
    && rm "node-v$NODE_VERSION.tar.xz" SHASUMS256.txt.asc SHASUMS256.txt; \
  fi \
  && rm -f "node-v$NODE_VERSION-linux-$ARCH-musl.tar.xz" \
  && apk del .build-deps \
  # smoke tests
  && node --version \
  && npm --version

RUN npm install -g pnpm@^9.3.0

COPY oh-my-zsh/.zshrc /bootstrap/.zshrc
COPY entrypoint.sh /bootstrap/entrypoint.sh
COPY symfony.ini /bootstrap/symfony.ini
COPY entrypoint.d/* /docker/entrypoint.d/
COPY oh-my-zsh/themes/arthem.zsh-theme /bootstrap/.oh-my-zsh/themes/arthem.zsh-theme
COPY php/dev-cli.ini $PHP_INI_DIR/conf.d/zz-dev-cli.ini

RUN chmod -R +x \
        /bootstrap/entrypoint.sh \
        /docker/entrypoint.d/*.sh

ENTRYPOINT ["/bootstrap/entrypoint.sh"]

CMD ["/bin/zsh"]
