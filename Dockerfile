# ==============================================================================
# Stage 1: Vendor Dependencies via Composer
# ==============================================================================
FROM composer:2.8 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ==============================================================================
# Stage 2: Production PHP-FPM + Nginx Runtime
# ==============================================================================
FROM php:8.4-fpm-bookworm

LABEL maintainer="Smile Concept"
ENV DEBIAN_FRONTEND=noninteractive
ENV PORT=10000

# Install production system dependencies and Nginx
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    gettext-base \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client \
    ca-certificates \
    curl \
    zip \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Production PHP & Opcache Configuration
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini \
    && echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Setup Nginx configuration template
RUN mkdir -p /etc/nginx/templates
COPY docker/nginx.conf /etc/nginx/templates/default.conf.template
RUN rm -f /etc/nginx/sites-enabled/default

# Set working directory
WORKDIR /var/www/html

# Copy vendor from Stage 1
COPY --from=vendor /app/vendor /var/www/html/vendor

# Copy application source code
COPY . /var/www/html

# Copy and setup entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Ensure storage and bootstrap permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose Render standard port
EXPOSE 10000

# Run entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
