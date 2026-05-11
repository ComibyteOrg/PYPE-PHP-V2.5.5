FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libxml2-dev \
    libonig-dev \
    git \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo pdo_mysql curl xml mbstring intl

RUN a2enmod rewrite
RUN sed -i 's|/var/www/html|/var/www/html|g' /etc/apache2/sites-available/000-default.conf

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN mkdir -p Storage/logs Storage/uploads Storage/sessions
RUN chmod -R 755 Storage/

RUN composer install --no-dev --optimize-autoloader

ENV APACHE_DOCUMENT_ROOT=/var/www/html
ENV APP_ENV=production
ENV APP_DEBUG=false

EXPOSE 80

CMD ["apache2-foreground"]
