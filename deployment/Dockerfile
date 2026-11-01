FROM composer:2 AS composer
FROM php:8.2-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libonig-dev libzip-dev default-mysql-client \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring bcmath zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY deployment/apache.conf /etc/apache2/sites-available/000-default.conf

ENV APACHE_DOCUMENT_ROOT=/var/www/app/public
WORKDIR /var/www/app

