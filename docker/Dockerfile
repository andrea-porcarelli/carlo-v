FROM php:8.3-fpm

# Installa dipendenze sistema
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git curl \
    libxml2-dev libpng-dev libjpeg-dev \
    && rm -rf /var/lib/apt/lists/*

# Installa estensioni PHP
RUN docker-php-ext-install pdo pdo_mysql zip gd

# Installa Redis - FIX per "Class Redis not found"
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && php -m | grep redis

# Installa Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
