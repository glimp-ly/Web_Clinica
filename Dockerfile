FROM php:8.3-apache

RUN docker-php-ext-install pdo pdo_mysql

RUN a2enmod rewrite

RUN sed -i 's/Listen 80/Listen 8989/' /etc/apache2/ports.conf

COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY frontend_clinica/ /var/www/frontend_clinica/
COPY backend_clinica/ /var/www/backend_clinica/

RUN chown -R www-data:www-data /var/www/frontend_clinica /var/www/backend_clinica \
    && chmod -R 755 /var/www/frontend_clinica /var/www/backend_clinica

EXPOSE 8989

CMD ["apache2-foreground"]
