FROM php:8.1-apache

COPY . .

RUN docker-php-ext-install mysqli

RUN chown -R www-data:www-data /var/www
