FROM php:8.2-apache

# Habilitar módulos de Apache
RUN a2enmod rewrite

# Establecer el directorio de trabajo
WORKDIR /var/www/html

# Copiar todos los archivos del proyecto
COPY . /var/www/html/

# Cambiar propietario de los archivos
RUN chown -R www-data:www-data /var/www/html

# Verificar que index.php existe (para depuración)
RUN ls -la /var/www/html/

# Exponer el puerto 80
EXPOSE 80

# Comando para iniciar Apache en primer plano
CMD ["apache2-foreground"]