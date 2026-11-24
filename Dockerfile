FROM php:8.4-apache-bookworm AS php-base

ENV PORT=10000 \
    COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev unzip \
    && docker-php-ext-install -j"$(nproc)" bcmath mbstring pdo_mysql opcache \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod headers \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

COPY docker/apache/ports.conf /etc/apache2/ports.conf
COPY docker/apache/vending.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/production.ini /usr/local/etc/php/conf.d/vending-production.ini
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

FROM php-base AS build

COPY . /var/www/html/
RUN composer install --no-dev --no-scripts --prefer-dist \
        --no-interaction --no-progress --optimize-autoloader

FROM php-base

COPY --from=build --chown=root:root /var/www/html/ /var/www/html/
# Code is read-only for Apache; only the log folder is writable.
RUN chown root:root /var/www/html && chmod 0755 /var/www/html \
    && chown www-data:www-data /var/www/html/storage/logs

EXPOSE 10000
CMD ["apache2-foreground"]
