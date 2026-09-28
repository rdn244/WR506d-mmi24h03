#!/bin/sh
set -e

if [ "$1" = 'php-fpm' ]; then
    if [ ! -f .env ] && [ -f .env.example ]; then
        echo "Création de .env à partir de .env.example"
        cp .env.example .env
    fi

    if [ -f composer.json ] && [ ! -f vendor/autoload_runtime.php ]; then
        composer install --prefer-dist --no-progress --no-interaction
    fi

    if [ -f bin/console ] && [ -d migrations ] && [ -n "$(ls -A migrations/*.php 2>/dev/null)" ]; then
        php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
    fi
fi

exec docker-php-entrypoint "$@"
