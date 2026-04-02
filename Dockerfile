# Multi-stage build for production optimization
FROM php:8.2-apache as base

# Install PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql zip opcache \
    && a2enmod rewrite headers

# Configure PHP for production
COPY docker/php.ini /usr/local/etc/php/

# Set working directory
WORKDIR /var/www/html

# Copy application
COPY . .

# Install Composer dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/public/uploads \
    && chmod -R 755 /var/www/html/storage /var/www/html/public/uploads

# Expose port
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

CMD ["apache2-foreground"]
