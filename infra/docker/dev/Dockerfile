FROM php:7.3-fpm-stretch as api_php

RUN set -eux \
    && apt-get update && apt-get install -y \
		libzip-dev \
		libicu-dev \
        unzip \
		git \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libicu-dev \
        pdftohtml \
    && pecl install -o -f redis xdebug-2.7.2 \
    && rm -rf /tmp/pear \
    && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
    && docker-php-ext-configure intl \
	&& docker-php-ext-install -j$(nproc) \
		intl \
		zip \
		pdo \
		gd \
		opcache \
		bcmath \
		sockets \
		pdo_mysql \
		intl \
    && docker-php-ext-enable redis \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN useradd -u 1000 -d /home/app -m app \
    && printf "\nuser = app\ngroup = app\n" >> /usr/local/etc/php-fpm.d/zz-docker.conf

ENV COMPOSER_ALLOW_SUPERUSER 1

RUN apt-get update && apt-get install -y \
        curl \
        nano \
        vim \
        git \
        iputils-ping \
        zsh \
        telnet \
        autoconf \
        unzip \
        libtool \
        pkg-config \
        nasm \
    && curl -LsS https://symfony.com/installer -o /usr/local/bin/symfony \
    && chmod a+x /usr/local/bin/symfony \
    && curl -sL https://deb.nodesource.com/setup_11.x | bash - \
    && curl -sS https://dl.yarnpkg.com/debian/pubkey.gpg | apt-key add - \
    && echo "deb https://dl.yarnpkg.com/debian/ stable main" | tee /etc/apt/sources.list.d/yarn.list \
    && apt-get update \
    && apt-get install -y \
        nodejs \
        yarn \
        libpng-dev \
        build-essential \
    && mkdir -p /bootstrap/entrypoint.d \
    && git clone https://github.com/robbyrussell/oh-my-zsh.git /bootstrap/.oh-my-zsh \
	&& rm -rf /var/lib/apt/lists/*

COPY oh-my-zsh/.zshrc /bootstrap/.zshrc
COPY entrypoint.sh /bootstrap/entrypoint.sh
COPY entrypoint.d/* /bootstrap/entrypoint.d/
COPY oh-my-zsh/themes/arthem.zsh-theme /bootstrap/.oh-my-zsh/themes/arthem.zsh-theme

RUN chmod -R +x \
        /bootstrap/entrypoint.sh \
        /bootstrap/entrypoint.d/*.sh

USER app

ENTRYPOINT ["/bootstrap/entrypoint.sh"]

CMD ["/bin/zsh"]
