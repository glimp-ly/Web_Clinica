FROM php:8.3-apache

# Instalar extensión PDO MySQL requerida para MariaDB
RUN docker-php-ext-install pdo pdo_mysql

# Habilitar mod_rewrite para el enrutamiento REST del backend en /api/
RUN a2enmod rewrite

# Configurar Apache para escuchar en el puerto 8989 solicitado
RUN sed -i 's/Listen 80/Listen 8989/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost \*:8989>/' /etc/apache2/sites-available/000-default.conf

# Permitir directivas de .htaccess (RewriteEngine, Headers, etc.)
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copiar archivos del proyecto al DocumentRoot
COPY frontend_clinica/ /var/www/html/

# Configurar permisos de lectura y ejecución para el servidor web
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 8989

CMD ["apache2-foreground"]
