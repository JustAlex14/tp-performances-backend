FROM php:8-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable pdo_mysql

EXPOSE 80

COPY index.php /var/www/html/index.php