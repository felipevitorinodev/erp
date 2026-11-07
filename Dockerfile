FROM php:8.4-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        nginx \
        unzip \
        libzip-dev \
        libpng-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libxml2-dev \
        libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        zip \
        bcmath \
        gd \
        intl \
        opcache \
        soap \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /var/lib/nginx/body /var/log/nginx /tmp/nginx/client_body /tmp/nginx/fastcgi \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx /tmp/nginx

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --no-interaction

COPY . .

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-erp.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-erp.conf

RUN composer dump-autoload --optimize --no-dev --no-interaction --no-scripts \
    && APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= \
        php artisan package:discover --ansi --no-interaction \
    && rm -f bootstrap/cache/config.php \
    && mkdir -p \
        storage/app/public \
        storage/app/private \
        storage/app/certificados \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache \
    && chmod +x docker/start.sh

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=5 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

CMD ["docker/start.sh"]
