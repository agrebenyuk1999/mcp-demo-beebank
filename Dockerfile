FROM php:8.4-cli-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

RUN echo "date.timezone=Europe/Kyiv" > "$PHP_INI_DIR/conf.d/timezone.ini"

RUN echo "opcache.revalidate_freq=0" > "$PHP_INI_DIR/conf.d/opcache-dev.ini"
