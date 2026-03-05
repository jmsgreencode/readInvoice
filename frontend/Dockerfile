FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    zip \
    unzip \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    && docker-php-ext-install \
    zip \
    intl \
    mbstring \
    opcache \
    pdo \
    pdo_mysql \
    && rm -rf /var/cache/apk/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Configure Nginx
COPY nginx.conf /etc/nginx/http.d/default.conf

# Configure supervisord
COPY supervisord.conf /etc/supervisord.conf

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for layer caching
COPY composer.json ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction 2>/dev/null || true

# Copy application source
COPY . .

# Install again with full source
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions and create required dirs
RUN mkdir -p /var/www/html/var/log /var/www/html/var/sessions \
    /var/log/nginx /var/run/nginx /var/lib/nginx/tmp \
    && chown -R www-data:www-data /var/www/html \
    && chown -R www-data:www-data /var/log/nginx /var/run/nginx /var/lib/nginx \
    && chmod -R 755 /var/www/html/public

EXPOSE 9001

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
