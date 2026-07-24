FROM php:8.5-fpm-alpine

# Extensions ueber den mlocati-Helper installieren. Er zieht automatisch die
# passenden Build- und Runtime-Bibliotheken, konfiguriert gd (freetype/jpeg)
# selbst, aktiviert die Extensions und raeumt die Build-Deps wieder auf.
# Robuster als ein gebuendelter docker-php-ext-install-Lauf (der auf neuen
# PHP-Versionen mit "cp: can't stat 'modules/*'" scheitern kann).
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions \
        gd \
        intl \
        mbstring \
        mysqli \
        opcache \
        pdo_mysql \
        zip

WORKDIR /var/www/html
