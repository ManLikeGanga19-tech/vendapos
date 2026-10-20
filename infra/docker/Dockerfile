FROM dunglas/frankenphp:1-php8.4-bookworm

LABEL maintainer="Daniel Oganga <danielorwenjo7@gmail.com>"

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    intl \
    bcmath \
    pdo_sqlite \
    pcntl \
    zip \
    gd \
    opcache \
    exif

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node.js 20 LTS (for Vite/Tailwind only)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# PHP config
COPY php.ini /usr/local/etc/php/conf.d/vendapos.ini

WORKDIR /app

EXPOSE 8000
