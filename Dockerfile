# syntax=docker/dockerfile:1.7
FROM composer:2.8 AS composer-bin
FROM php:8.4-fpm-bookworm AS php-base
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN apt-get update && apt-get install -y --no-install-recommends curl libfreetype6-dev libicu-dev libjpeg62-turbo-dev libonig-dev libpng-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" bcmath gd intl mbstring opcache pcntl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer-bin /usr/bin/composer /usr/bin/composer
COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-www.conf
WORKDIR /var/www/html
FROM php-base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --optimize-autoloader
FROM node:22-bookworm-slim AS assets
WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci --include=optional
COPY --from=vendor /var/www/html/vendor ./vendor
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build
FROM php-base AS production
ARG APP_UID=1000
ARG APP_GID=1000
RUN groupadd --gid "${APP_GID}" app && useradd --uid "${APP_UID}" --gid "${APP_GID}" --create-home --shell /usr/sbin/nologin app
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=assets /var/www/html/public/build ./public/build
COPY . .
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && rm -rf public/storage \
    && ln -s ../storage/app/public public/storage \
    && chown -R app:app storage bootstrap/cache
ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["php-fpm", "-F"]
FROM nginx:1.27-alpine AS nginx
COPY --from=production /var/www/html/public /var/www/html/public
RUN mkdir -p /var/www/html/storage/app/public \
    && rm -rf /var/www/html/public/storage \
    && ln -s ../storage/app/public /var/www/html/public/storage
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
