FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    curl \
    git \
    libzip-dev \
    libonig-dev \
    libicu-dev \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    libfreetype6-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        zip \
        bcmath \
        exif \
        intl \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install gd

RUN docker-php-ext-enable exif

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

RUN echo "memory_limit=1024M" > /usr/local/etc/php/conf.d/memory-limit.ini
