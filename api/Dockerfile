# syntax=docker/dockerfile:1.7
#
# Production-style image for the Laravel API (php-fpm).
# Build args let builds inside Iran point at local mirrors when international links are down.

ARG PHP_IMAGE=php:8.3-fpm-alpine

FROM ${PHP_IMAGE} AS base
RUN apk add --no-cache icu-libs libpq libzip \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev postgresql-dev libzip-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql bcmath opcache pcntl zip \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
WORKDIR /var/www/api

FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ARG COMPOSER_REPO=https://repo.packagist.org
RUN composer config -g repos.packagist composer "${COMPOSER_REPO}"
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

FROM base AS app
COPY --from=vendor --chown=www-data:www-data /var/www/api /var/www/api
USER www-data
EXPOSE 9000
CMD ["php-fpm"]
