FROM php:8.2-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql && docker-php-ext-enable mysqli pdo_mysql
RUN a2enmod rewrite headers

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Support Render dynamic PORT
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE 80
