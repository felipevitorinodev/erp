FROM php:8.4-apache

# =========================
# Dependências do sistema
# =========================
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libzip-dev

# =========================
# Extensões PHP
# =========================
RUN docker-php-ext-install pdo pdo_mysql zip

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
# SSL Aiven (IMPORTANTE)
# =========================
RUN mkdir -p /etc/ssl/aiven \
 && curl -o /etc/ssl/aiven/ca.pem https://raw.githubusercontent.com/Aiven-Labs/certificates/main/aiven-ca.pem

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
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# =========================
# Porta
# =========================
EXPOSE 80

COPY docker-entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

CMD ["/entrypoint.sh"]