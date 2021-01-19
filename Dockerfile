FROM php:7.3-apache

ENV COMPOSER_HOME=/var/www/composer

# Update and install
RUN apt-get update

RUN apt-get install -y --no-install-recommends libjpeg-dev libpng-dev libzip-dev vim unzip wget
RUN docker-php-ext-configure gd --with-png-dir=/usr --with-jpeg-dir=/usr
RUN docker-php-ext-install gd mysqli opcache zip

RUN a2enmod rewrite

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php --install-dir=/bin --filename=composer
RUN php -r "unlink('composer-setup.php');"

RUN chown -R 1000.1000 /var/www
RUN chmod ug=rwx+s /var/www

# User settings
RUN usermod -u 1000 www-data
RUN groupmod -g 1000 www-data

RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/conf.d/php.ini

USER 1000
RUN wget https://br.wordpress.org/latest-pt_BR.zip -P /var/www
RUN unzip /var/www/latest-pt_BR.zip -d /var/www/wordpress
RUN cp -R /var/www/wordpress/wordpress/* /var/www/html
RUN rm -rf /var/www/wordpress

RUN wget https://downloads.wordpress.org/plugin/woocommerce.4.9.0.zip -P /var/www
RUN unzip /var/www/woocommerce.4.9.0.zip -d /var/www/html/wp-content/plugins

USER root
