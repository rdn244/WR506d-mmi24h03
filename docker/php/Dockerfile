FROM php:8.5-fpm

# Installeur d'extensions PHP (gère les dépendances système)
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && install-php-extensions intl pdo_mysql zip apcu

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]
