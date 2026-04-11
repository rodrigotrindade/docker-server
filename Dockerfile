FROM php:7.4-fpm

# Instala extensões necessárias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# (Opcional) outras úteis
# RUN docker-php-ext-install gd mbstring zip