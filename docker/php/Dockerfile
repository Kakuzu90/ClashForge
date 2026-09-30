# PHP 8.3 FPM image for the ClashForge (Clash Commons) Laravel app (lives in ../../src).
# No Redis: cache/queue use the database driver (see specs/21-caching-strategy.md).
FROM php:8.3-fpm-alpine

ENV COMPOSER_ALLOW_SUPERUSER=1

# System deps + PHP extensions Laravel + Postgres + image work need.
# pcntl is required for queue:work; gd/exif for Intervention Image; pdo_pgsql/pgsql for Postgres;
# ffmpeg/ffprobe for video transcoding on the media worker (specs/10 §6).
RUN apk add --no-cache bash git curl postgresql-client libstdc++ ffmpeg \
 && curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
      -o /usr/local/bin/install-php-extensions \
 && chmod +x /usr/local/bin/install-php-extensions \
 && install-php-extensions pdo_pgsql pgsql gd zip bcmath intl pcntl opcache exif \
 && rm /usr/local/bin/install-php-extensions

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node:22-alpine /usr/local/bin/node /usr/local/bin/node
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zzz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint \
 && chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
