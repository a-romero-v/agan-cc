FROM php:8.5-cli-alpine

# pdo_sqlite and opcache ship with the official image; only intl is missing.
RUN apk add --no-cache icu-libs \
    && apk add --no-cache --virtual .build-deps icu-dev $PHPIZE_DEPS \
    && docker-php-ext-install intl \
    && apk del .build-deps

# Do not advertise the PHP version in an X-Powered-By header.
RUN echo 'expose_php=0' > "$PHP_INI_DIR/conf.d/app.ini"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# PHP's built-in server; PHP_CLI_SERVER_WORKERS (see compose.yaml) allows parallel requests.
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public/"]
