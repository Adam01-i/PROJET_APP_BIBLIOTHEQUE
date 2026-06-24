FROM php:8.3-apache

# Extensions nécessaires
RUN docker-php-ext-install pdo pdo_mysql

# Activation des modules Apache nécessaires
RUN a2enmod rewrite headers

# Copier le projet
COPY . /var/www/html

# Document root = public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
/etc/apache2/sites-available/*.conf

RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
/etc/apache2/apache2.conf \
/etc/apache2/conf-available/*.conf

EXPOSE 80