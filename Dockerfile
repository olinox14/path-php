FROM php:8.3-cli

# Installer les dépendances pour l'extension zip
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
  && docker-php-ext-install zip;

RUN pecl install xdebug; \
    docker-php-ext-enable xdebug;

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /path

CMD ["tail", "-f", "/dev/null"]
