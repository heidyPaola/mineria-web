FROM php:8.2-apache

# Habilitar módulos de Apache
RUN a2enmod rewrite

# Copiar todos los archivos del proyecto al servidor
COPY . /var/www/html/

# Dar permisos
RUN chown -R www-data:www-data /var/www/html

# Exponer el puerto 80
EXPOSE 80