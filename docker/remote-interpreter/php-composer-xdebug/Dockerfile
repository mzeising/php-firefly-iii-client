FROM php:8.2-fpm-bullseye

RUN set -eux && \
    apt update -y && \
    apt install -y zip libzip-dev && \
    docker-php-ext-install zip && \
    # xdebug
    pecl install xdebug && \
    docker-php-ext-enable xdebug && \
    # Composer
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');" && \
    # PHPUnit
    mkdir /app && \
    composer require phpunit/phpunit ^9 --no-interaction --working-dir=/app
