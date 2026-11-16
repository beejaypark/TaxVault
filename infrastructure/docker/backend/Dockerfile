FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libxml2-dev \
        curl \
        poppler-utils \
        tesseract-ocr \
    && docker-php-ext-install \
        bcmath \
        xml \
        pdo_pgsql \
        pgsql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php-upload.ini /usr/local/etc/php/conf.d/99-taxvault-upload.ini

COPY composer.json composer.lock ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --no-scripts

COPY . .
COPY docker/entrypoint.sh /usr/local/bin/taxvault-entrypoint

RUN composer dump-autoload --optimize \
    && chmod +x /usr/local/bin/taxvault-entrypoint

EXPOSE 8000

ENTRYPOINT ["taxvault-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
