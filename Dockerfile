FROM php:8.2-apache

# Instalar extensiones necesarias para MySQL
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install zip \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install mysqli

# Habilitar módulos de Apache
RUN a2enmod rewrite

# Copiar todos los archivos del proyecto al servidor
COPY . /var/www/html/

# Dar permisos
RUN chown -R www-data:www-data /var/www/html

# Exponer el puerto 80
EXPOSE 80