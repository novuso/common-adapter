FROM php:8-cli

RUN apt-get update \
    && apt-get install -y --allow-downgrades --allow-remove-essential --allow-change-held-packages \
        wget \
        curl \
        git \
        locales \
        libxml2-dev \
        libssl-dev \
        libzip-dev \
    && echo "en_US.UTF-8 UTF-8" >> /etc/locale.gen \
    && locale-gen en_US.UTF-8 \
    && dpkg-reconfigure --frontend=noninteractive locales \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl \
    && docker-php-ext-install pcntl \
    && docker-php-ext-install pdo \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install phar \
    && docker-php-ext-install simplexml \
    && docker-php-ext-install zip \
    && docker-php-ext-install bcmath \
    && docker-php-ext-install sockets \
    && docker-php-ext-install posix \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /vat/tmp/*

RUN echo 'zend.assertions = 1' >> /usr/local/etc/php/conf.d/custom.ini \
    && echo 'assert.exception = 1' >> /usr/local/etc/php/conf.d/custom.ini \
    && echo 'xdebug.mode = coverage' >> /usr/local/etc/php/conf.d/custom.ini
