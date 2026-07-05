FROM php:8.4-apache

# =========================
# Dependências do sistema
# =========================
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libzip-dev \
    libssl-dev

# =========================
# Extensões PHP
# =========================
RUN docker-php-ext-install pdo pdo_mysql zip

# Garante OpenSSL ativo (CRÍTICO pro Aiven)
RUN docker-php-ext-enable openssl

# =========================
# Apache config
# =========================
RUN a2enmod rewrite

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf

RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# =========================
# SSL Aiven CA
# =========================
RUN mkdir -p /etc/ssl/aiven \
 && curl -fsSL -o /etc/ssl/aiven/ca.pem \
 https://raw.githubusercontent.com/Aiven-Labs/certificates/main/aiven-ca.pem

# =========================
# Composer
# =========================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# =========================
# App
# =========================
WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

# =========================
# Permissões Laravel
# =========================
RUN mkdir -p storage bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# =========================
# Cache safety (evita erro 500 por config antiga)
# =========================
RUN php artisan config:clear || true
RUN php artisan cache:clear || true

# =========================
# Porta
# =========================
EXPOSE 80

CMD ["apache2-foreground"]