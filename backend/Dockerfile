FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache \
    tesseract-ocr \
    tesseract-ocr-data-eng \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    nginx \
    supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mbstring intl zip opcache bcmath \
    && rm -rf /var/cache/apk/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN addgroup -g 1000 -S appgroup && adduser -u 1000 -S appuser -G appgroup

WORKDIR /var/www/html

COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY php.ini /usr/local/etc/php/conf.d/app.ini
COPY php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY nginx.conf /etc/nginx/http.d/default.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN mkdir -p /var/www/uploads /var/log/nginx /var/run/nginx \
    && chown -R appuser:appgroup /var/www /var/log/nginx /var/run/nginx /var/lib/nginx

EXPOSE 8080

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
