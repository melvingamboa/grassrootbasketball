FROM php:8.4-cli-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        libicu-dev \
        libsqlite3-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_sqlite \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer
COPY docker/api/php.ini /usr/local/etc/php/conf.d/grassroots.ini
COPY docker/api/entrypoint.sh /usr/local/bin/app-entrypoint

RUN chmod +x /usr/local/bin/app-entrypoint \
    && groupmod -o -g "${GID}" www-data \
    && usermod -o -u "${UID}" -g www-data www-data

WORKDIR /var/www/html

EXPOSE 8000
