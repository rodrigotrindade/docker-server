FROM php:7.4-apache

RUN docker-php-ext-install mysqli

COPY php.ini /usr/local/etc/php/
