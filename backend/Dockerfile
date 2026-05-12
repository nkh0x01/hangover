# syntax=docker/dockerfile:1.7
# Multi-stage build for the Hangover backend.
# One image, multiple roles selected by entrypoint: api | horizon | reverb | scheduler.

# ---------- base (php-fpm + nginx + extensions) ----------
FROM php:8.3-fpm-alpine AS base

ARG APP_USER=app
ARG APP_UID=1000
ARG APP_GID=1000

RUN apk add --no-cache \
        nginx \
        bash \
        curl \
        git \
        icu-data-full \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        zip \
        unzip \
        autoconf \
        build-base \
        linux-headers \
        supervisor \
        tzdata \
        nodejs \
        npm \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        bcmath \
        intl \
        mbstring \
        opcache \
        sockets \
        exif \
        pcntl \
    && pecl install redis-6.0.2 igbinary-3.2.15 \
    && docker-php-ext-enable redis igbinary \
    && apk del --no-network autoconf build-base linux-headers \
    && rm -rf /var/cache/apk/* /tmp/pear

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Non-root user
RUN addgroup -g ${APP_GID} ${APP_USER} \
    && adduser -D -u ${APP_UID} -G ${APP_USER} -h /home/${APP_USER} -s /bin/bash ${APP_USER}

# PHP / OPcache / nginx / supervisor configs
COPY docker/php.ini           /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/opcache.ini       /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/php-fpm.conf      /usr/local/etc/php-fpm.d/zz-www.conf
COPY docker/nginx.conf        /etc/nginx/nginx.conf
COPY docker/supervisord.conf  /etc/supervisor/conf.d/supervisord.conf

# Entrypoints — one per role
COPY docker/entrypoints/api.sh       /usr/local/bin/entrypoint-api.sh
COPY docker/entrypoints/horizon.sh   /usr/local/bin/entrypoint-horizon.sh
COPY docker/entrypoints/reverb.sh    /usr/local/bin/entrypoint-reverb.sh
COPY docker/entrypoints/scheduler.sh /usr/local/bin/entrypoint-scheduler.sh
RUN chmod +x /usr/local/bin/entrypoint-*.sh

WORKDIR /var/www/html

# ---------- dev image (bind-mounts source; installs xdebug for coverage) ----------
FROM base AS dev

RUN pecl install pcov-1.0.11 && docker-php-ext-enable pcov

ENV APP_ENV=local \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=1

EXPOSE 8000
CMD ["/usr/local/bin/entrypoint-api.sh"]

# ---------- builder (composer install for prod) ----------
FROM base AS builder

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-progress

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && php artisan optimize \
    && chown -R ${APP_USER}:${APP_USER} /build

# ---------- prod image ----------
FROM base AS prod

ENV APP_ENV=production \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

COPY --from=builder --chown=${APP_USER}:${APP_USER} /build /var/www/html

USER ${APP_USER}

EXPOSE 8000
CMD ["/usr/local/bin/entrypoint-api.sh"]
