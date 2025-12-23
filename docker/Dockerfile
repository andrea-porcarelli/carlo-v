FROM php:8.3-fpm

# Installa dipendenze sistema (incluse librerie per estensioni PHP)
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip git curl \
    libxml2-dev libpng-dev libjpeg-dev \
    libicu-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Configura e installa estensioni PHP
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_mysql zip gd mbstring intl

# Installa Redis - FIX per "Class Redis not found"
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && php -m | grep redis

# Installa Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configura Git per evitare warning di ownership
RUN git config --global --add safe.directory /var/www/html

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
