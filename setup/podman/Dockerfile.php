FROM php:8.5-fpm-alpine

# Runtime-Bibliotheken bleiben im finalen Image; Compiler und *-dev-Pakete
# werden nach dem Bau der PHP-Extensions wieder entfernt.
RUN set -eux; \
    apk add --no-cache \
        freetype \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libzip \
        oniguruma; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libzip-dev \
        oniguruma-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        mbstring \
        mysqli \
        opcache \
        pdo_mysql \
        zip; \
    apk del .build-deps

WORKDIR /var/www/html
