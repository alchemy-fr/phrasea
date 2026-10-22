FROM php:8.5.12-cli-alpine3.24

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
        fontconfig \
        ttf-dejavu \
        ttf-liberation \
        font-carlito \  
        font-noto \     
        font-noto-cjk \ 
        font-noto-emoji \
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

ENV APCU_VERSION=5.1.28 \
    AMQP_VERSION=2.2.0 \
    XDEBUG_VERSION=3.5.0 \
    REDIS_VERSION=6.3.0 \
    APP_ENV=dev

ENV IMAGICK_VERSION=3.8.1 \
    IMAGEMAGICK_POLICY_VERSION=7 \
    IMAGEMAGICK_POLICY_WIDTH=48KP \
    IMAGEMAGICK_POLICY_MAP=2048MiB \
    IMAGEMAGICK_POLICY_AREA=4096MB \
    IMAGEMAGICK_POLICY_DISK=6GiB \
    IMAGEMAGICK_POLICY_TEMPORARY_PATH=/tmp

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
        openssl-dev \
	; \
	\
	docker-php-ext-configure ftp --with-ftp-ssl; \
	docker-php-ext-install \
        exif \
        ftp \
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
        imagick-${IMAGICK_VERSION} \
		xdebug-${XDEBUG_VERSION} \
	; \
	pecl clear-cache; \
	docker-php-ext-enable \
		apcu \
		amqp \
        imagick \
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

COPY --from=composer:2.9.3 /usr/bin/composer /usr/bin/composer

ENV NODE_VERSION=26.12.1 \
    NODE_OPTIONS=--openssl-legacy-provider

RUN apk add --no-cache \
        libatomic \
        libstdc++ \
    && apk add --no-cache --virtual .build-deps \
        curl \
    && ARCH= && alpineArch="$(apk --print-arch)" \
      && case "${alpineArch##*-}" in \
        x86_64) \
          ARCH='x64' \
          CHECKSUM="5c3834652a0fb8f204e6df76be1469aa99a9d18011fb79cc85d97c822184d976" \
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
        py-setuptools \
        rust \
        cargo \
    # gpg keys listed at https://github.com/nodejs/node#release-keys
    && for key in \
      5BE8A3F6C8A5C01D106C0AD820B1A390B168D356 \
      DD792F5973C6DE52C432CBDAC77ABFA00DDBF2B7 \
      CC68F5A3106FF448322E48ED27F5E38D5B0A215F \
      890C08DB8579162FEE0DF9DB8BEAB4DFCF555EF4 \
      C82FA3AE1CBEDC6BE46B9360C43CEC45C17AB93C \
      108F52B48DB57BB0CC439B2997B01419BD92F80A \
      655F3B5C1FB3FA8D1A0CA6BDE4A7D232B936D2FD \
      A363A499291CBBC940DD62E41F10027AF002F8B0 \
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

RUN npm install -g pnpm@^12.15.0

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
