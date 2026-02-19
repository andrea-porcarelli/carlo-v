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

# Configura Git safe.directory per tutti gli utenti (system-wide)
RUN git config --system --add safe.directory /var/www/html

# Aggiunge www-data al gruppo 1001 (gid del proprietario dei file sul host)
# così PHP-FPM può scrivere nella directory .git montata dal host
RUN groupadd -g 1001 hostfiles 2>/dev/null || true && \
    usermod -aG 1001 www-data && \
    usermod -d /var/www www-data

WORKDIR /var/www/html

# Entrypoint: fix .git ownership at container startup so www-data can run git pull
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
