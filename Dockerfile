FROM php:8.3

RUN apt-get update && apt-get install -y \
    libc6-dev \
    libsasl2-dev \
    libsasl2-modules \
    libssl-dev \
    libzip-dev \
    zip


RUN docker-php-ext-install pdo pdo_mysql zip exif
RUN docker-php-ext-enable exif

RUN curl -sS https://getcomposer.org/installer | php -- \
        --install-dir=/usr/local/bin --filename=composer

RUN apt-get update && apt-get install -y libxml2-dev \
    && docker-php-ext-install soap

USER 1000:1000


WORKDIR /app
