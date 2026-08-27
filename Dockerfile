FROM php:8.3-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl wget unzip \
    libpng-dev libjpeg-dev libfreetype6-dev \
    libzip-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip intl pdo pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY fichaje-smd/ .

# Set document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

# Laravel permissions
RUN chown -R www-data:www-data . && \
    chmod -R 755 . && \
    chmod -R 777 storage bootstrap/cache

EXPOSE 80

CMD ["apache2-foreground"]
