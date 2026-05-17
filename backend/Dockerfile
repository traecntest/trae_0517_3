FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    autoconf \
    g++ \
    make \
    pdo_mysql

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json ./

RUN composer install --no-scripts --no-autoloader

COPY . .

RUN composer dump-autoload

RUN chmod -R 777 storage bootstrap/cache

EXPOSE 8000

CMD php -S 0.0.0.0:8000 -t public
